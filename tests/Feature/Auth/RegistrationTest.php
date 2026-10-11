<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('registration screen explains the production password rules', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('At least 12 characters, with upper and lower case letters, a number and a symbol.');
});

test('registration screen omits the production password rules elsewhere', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertDontSee('At least 12 characters');
});
