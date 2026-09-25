<?php

use App\Livewire\OnlineUsers;
use App\Models\User;
use Livewire\Livewire;

it('renders for authenticated user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(OnlineUsers::class)
        ->assertOk();
});

it('shows other users in the list', function () {
    $auth = User::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($auth)
        ->test(OnlineUsers::class)
        ->assertSee($other->name);
});

it('gives each row valid Alpine data for the call button', function () {
    $auth = User::factory()->create();
    $other = User::factory()->create(['name' => "O'Brien \"Ob\""]);

    Livewire::actingAs($auth)
        ->test(OnlineUsers::class)
        ->assertDontSeeHtml('@js(')
        ->assertSeeHtml('x-data="{ userId: '.$other->id.', userName: '.e(Js::from($other->name)).' }"');
});

it('does not show the authenticated user in the list', function () {
    $auth = User::factory()->create();
    User::factory()->create();

    Livewire::actingAs($auth)
        ->test(OnlineUsers::class)
        ->assertDontSee($auth->name);
});
