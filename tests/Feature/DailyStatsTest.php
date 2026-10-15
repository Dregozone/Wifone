<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['services.stats.token' => 'test-stats-token']);
});

it('returns the requested daily counts and active user windows', function () {
    $this->travelTo('2026-10-13 12:00:00');

    $firstUser = User::factory()->create([
        'created_at' => '2026-10-11 23:00:00',
        'last_active_at' => '2026-10-13 11:59:00',
    ]);
    $secondUser = User::factory()->create([
        'created_at' => '2026-10-12 22:59:59',
        'last_active_at' => '2026-10-06 12:00:00',
    ]);
    User::factory()->create([
        'created_at' => '2026-10-12 23:00:00',
        'last_active_at' => '2026-09-20 12:00:00',
    ]);
    User::factory()->create([
        'last_active_at' => '2026-09-13 11:59:59',
    ]);
    User::factory()->create();

    DB::table('logins')->insert([
        ['user_id' => $firstUser->id, 'logged_in_at' => '2026-10-11 23:30:00'],
        ['user_id' => $firstUser->id, 'logged_in_at' => '2026-10-12 22:30:00'],
        ['user_id' => $secondUser->id, 'logged_in_at' => '2026-10-12 22:59:59'],
        ['user_id' => $secondUser->id, 'logged_in_at' => '2026-10-12 23:00:00'],
    ]);

    $this->withToken('test-stats-token')
        ->getJson('/api/stats/daily?date=2026-10-12')
        ->assertExactJson([
            'app' => 'Wifone',
            'date' => '2026-10-12',
            'signups' => 2,
            'logins' => ['total' => 3, 'unique' => 2],
            'users' => ['total' => 5, 'active_7d' => 2, 'active_30d' => 3],
        ]);
});

it('defaults to yesterday in Europe London', function () {
    $this->travelTo('2026-10-12 23:30:00');

    $this->withToken('test-stats-token')
        ->getJson('/api/stats/daily')
        ->assertOk()
        ->assertJsonPath('date', '2026-10-12');
});

it('returns 401 when no bearer token is provided', function () {
    $this->getJson('/api/stats/daily')->assertUnauthorized();
});

it('returns 401 when the bearer token is wrong', function () {
    $this->withToken('wrong-token')
        ->getJson('/api/stats/daily')
        ->assertUnauthorized();
});

it('returns 404 when no stats token is configured', function () {
    config(['services.stats.token' => null]);

    $this->withToken('test-stats-token')
        ->getJson('/api/stats/daily')
        ->assertNotFound();
});

it('rejects dates that do not use the YYYY-MM-DD format', function () {
    $this->withToken('test-stats-token')
        ->getJson('/api/stats/daily?date=12-10-2026')
        ->assertUnprocessable();
});

it('records one login row for a successful login', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseCount('logins', 1);
    $this->assertDatabaseHas('logins', ['user_id' => $user->id]);
});
