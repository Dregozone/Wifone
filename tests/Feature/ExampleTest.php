<?php

use App\Models\User;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('welcome page invites guests to sign up or log in', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Wifone')
        ->assertSee(route('login'), false)
        ->assertSee(route('register'), false)
        ->assertDontSee('Laravel has an incredibly rich ecosystem');
});

test('welcome page links signed in users to their contacts', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Open your contacts');
});
