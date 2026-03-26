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

it('does not show the authenticated user in the list', function () {
    $auth = User::factory()->create();
    User::factory()->create();

    Livewire::actingAs($auth)
        ->test(OnlineUsers::class)
        ->assertDontSee($auth->name);
});
