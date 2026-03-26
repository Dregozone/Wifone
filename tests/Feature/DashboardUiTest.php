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
