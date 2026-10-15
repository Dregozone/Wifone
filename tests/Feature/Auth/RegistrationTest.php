<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());

    config(['wifone.access_code' => 'Open-Sesame']);
});

function registerWithAccessCode(string $accessCode = 'Open-Sesame'): TestResponse
{
    return test()->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'access_code' => $accessCode,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen asks for the access code', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="access_code"', false);
});

test('new users can register', function () {
    registerWithAccessCode()->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('the access code is accepted in any case, with spaces around it', function () {
    registerWithAccessCode('  oPEN-sESAME ')->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
    expect(User::sole()->getAttributes())->not->toHaveKey('access_code');
});

test('a wrong access code is refused and no user is created', function () {
    registerWithAccessCode('open-sesame-please')->assertSessionHasErrors(['access_code' => "That access code isn't right."]);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('a missing access code is refused', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['access_code' => "That access code isn't right."]);

    expect(User::count())->toBe(0);
});

test('with no access code set, sign up is closed', function (?string $accessCode) {
    config(['wifone.access_code' => $accessCode]);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Sign up is closed, no invitation code is active.')
        ->assertDontSee('name="access_code"', false)
        ->assertDontSee('data-test="register-user-button"', false);

    registerWithAccessCode('')->assertForbidden();
    registerWithAccessCode('anything')->assertForbidden();

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with(['missing' => null, 'empty' => '', 'only spaces' => '   ']);

test('the sixth wrong access code within an hour is throttled', function () {
    foreach (range(1, 5) as $attempt) {
        registerWithAccessCode('wrong')->assertSessionHasErrors(['access_code' => "That access code isn't right."]);
    }

    registerWithAccessCode('wrong')->assertSessionHasErrors(['access_code' => 'Too many attempts, try again later']);
    registerWithAccessCode()->assertSessionHasErrors(['access_code' => 'Too many attempts, try again later']);
    expect(User::count())->toBe(0);

    $this->travel(61)->minutes();

    registerWithAccessCode()->assertSessionHasNoErrors();
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
