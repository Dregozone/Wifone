<?php

use App\Models\User;

test('dashboard contains incoming call modal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="incoming-call-modal"', false);
});

test('dashboard contains remote audio element', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="remote-audio"', false);
});

test('dashboard contains in-call ui overlay', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="in-call-ui"', false);
});

test('ice servers include every configured turn url', function () {
    config([
        'services.turn.urls' => ['turn:relay.example.com:80', 'turns:relay.example.com:443?transport=tcp'],
        'services.turn.username' => 'user',
        'services.turn.credential' => 'secret',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('stun:stun.l.google.com:19302', false)
        ->assertSee('turns:relay.example.com:443?transport=tcp', false);
});

test('ice servers are stun only when turn is not configured', function () {
    config(['services.turn.urls' => []]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('turn:', false);
});
