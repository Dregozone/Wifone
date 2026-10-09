<?php

use App\CallStatus;
use App\Models\Call;
use App\Models\User;

/*
 * Every page loads in a real browser without JavaScript errors or accessibility problems
 * (axe, every impact level), in light and dark mode, and fits a phone screen.
 */

beforeEach(function (): void {
    // Reverb isn't running under test; nothing here depends on events arriving.
    config(['broadcasting.default' => 'null']);
});

dataset('appearances', [
    'light' => 'inLightMode',
    'dark' => 'inDarkMode',
]);

it('renders the guest pages cleanly', function (string $path, string $appearance): void {
    $page = visit($path)->{$appearance}()->assertNoJavaScriptErrors();

    assertAccessibleOnceSettled($page);
})->with([
    'welcome' => '/',
    'login' => '/login',
    'register' => '/register',
    'forgot password' => '/forgot-password',
])->with('appearances');

it('renders the signed-in pages cleanly', function (string $routeName, string $appearance): void {
    $me = User::factory()->create();
    $sam = User::factory()->create(['name' => 'Sam Rivers']);
    // Recents shows a completed outgoing call and a missed incoming one (in red).
    Call::factory()->completed()->for($me, 'caller')->for($sam, 'receiver')->create();
    Call::factory()->for($sam, 'caller')->for($me, 'receiver')->create(['status' => CallStatus::Missed, 'ended_at' => now()]);
    $this->actingAs($me);

    $page = visit(route($routeName))->{$appearance}()->assertNoJavaScriptErrors();

    assertAccessibleOnceSettled($page);
})->with([
    'contacts' => 'dashboard',
    'recents' => 'calls.index',
    'profile' => 'profile.edit',
    'appearance' => 'appearance.edit',
])->with('appearances');

it('fits a phone screen without sideways scrolling', function (string $routeName): void {
    $this->actingAs(User::factory()->create());
    User::factory()->create(['name' => 'Someone With A Really Quite Long Name For A Phone Screen']);

    visit(route($routeName))
        ->on()->iPhoneSE()
        ->assertVisible('nav[aria-label="Main"]')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with(['dashboard', 'calls.index', 'profile.edit']);
