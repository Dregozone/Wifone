<?php

use App\CallStatus;
use App\Models\Call;
use App\Models\User;

/*
 * End-to-end through the browser: signing in, finding someone, and answering the call UI.
 * Reverb isn't running under test, so server events are fed straight into the Alpine call
 * store, exactly as Echo would; the HTTP requests the UI makes are real.
 */

beforeEach(function (): void {
    config(['broadcasting.default' => 'null']);
});

it('signs in and lands on the contacts list', function (): void {
    $user = User::factory()->create(['email' => 'alex@example.com']);
    User::factory()->create(['name' => 'Sam Rivers']);

    visit('/login')
        ->fill('email', 'alex@example.com')
        ->fill('password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard')
        ->assertSee('Contacts')
        ->assertSee('Sam Rivers')
        ->assertNoJavaScriptErrors();

    $this->assertAuthenticatedAs($user);
});

it('filters contacts as you type', function (): void {
    $this->actingAs(User::factory()->create());
    User::factory()->create(['name' => 'Sam Rivers']);
    User::factory()->create(['name' => 'Jo Marsh']);

    visit(route('dashboard'))
        ->fill('[aria-label="Search people"]', 'riv')
        ->assertSee('Sam Rivers')
        ->assertDontSee('Jo Marsh')
        ->fill('[aria-label="Search people"]', 'zzz')
        ->assertSee('No one matches your search.')
        ->assertNoJavaScriptErrors();
});

/**
 * Make the signed-in browser ring as if Echo had delivered call.initiated.
 */
function ringIncoming(Call $call): string
{
    return sprintf(
        "Alpine.store('call').onIncoming({ callId: %d, callerId: %d, callerName: %s })",
        $call->id,
        $call->caller_id,
        json_encode($call->caller->name),
    );
}

it('shows an accessible incoming call screen and records a decline', function (): void {
    $call = Call::factory()->create();
    $this->actingAs($call->receiver);

    $page = visit(route('dashboard'));
    $page->script(ringIncoming($call));

    $page->assertVisible('#incoming-call-modal')
        ->assertSeeIn('#incoming-call-modal', $call->caller->name)
        ->assertScript("document.activeElement.getAttribute('aria-label')", 'Accept')
        ->assertScript('document.title.startsWith("📞")', true);

    assertAccessibleOnceSettled($page);

    $page->click('[aria-label="Decline"]')
        ->assertMissing('#incoming-call-modal')
        ->assertNoJavaScriptErrors();

    expect($call->fresh()->status)->toBe(CallStatus::Rejected);
});

it('stops ringing and says so when the caller gives up', function (): void {
    $call = Call::factory()->create();
    $this->actingAs($call->receiver);

    $page = visit(route('dashboard'));
    $page->script(ringIncoming($call));
    $page->script("Alpine.store('call').onEnded({ callId: {$call->id}, status: 'missed' })");

    $page->assertMissing('#incoming-call-modal')
        ->assertSeeIn('#call-notice', "Missed call from {$call->caller->name}.")
        ->assertNoJavaScriptErrors();
});

it('warns when the live connection drops and disables calling', function (): void {
    $this->actingAs(User::factory()->create());

    $page = visit(route('dashboard'));
    $page->script("Alpine.store('presence').connected = false");

    $page->assertVisible('#connection-lost')
        ->assertSee('Connection lost.')
        ->assertNoJavaScriptErrors();
});
