<?php

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Models\CallSchedule;
use App\Models\User;

/*
 * Scheduling a call from a contact's card, answering the invite, and starting the call in its
 * window. Reverb isn't running under test, so presence is set straight in the Alpine store.
 */

beforeEach(function (): void {
    config(['broadcasting.default' => 'null']);
});

it('schedules a weekly call from a contact\'s card', function (): void {
    $me = User::factory()->create();
    $sam = User::factory()->create(['name' => 'Sam Rivers']);
    $this->actingAs($me);

    $page = visit(route('dashboard'))
        ->click('[aria-label="Schedule a call with Sam Rivers"]:visible')
        ->assertSee('Pick a time to call Sam Rivers.');

    assertAccessibleOnceSettled($page);

    $page->fill('date', now()->addWeek()->toDateString())
        ->fill('time', '19:00')
        ->select('repeat', 'weekly')
        ->assertSee('End date')
        ->press('Send invite')
        ->assertSee('Invite sent to Sam Rivers.')
        ->assertNoJavaScriptErrors();

    expect(CallSchedule::sole())
        ->caller_id->toBe($me->id)
        ->callee_id->toBe($sam->id)
        ->repeat->toBe(CallScheduleRepeat::Weekly)
        ->status->toBe(CallScheduleStatus::Pending);
});

it('accepts an invite from the Invites page', function (): void {
    $me = User::factory()->create();
    $schedule = CallSchedule::factory()->for(User::factory()->create(['name' => 'Sam Rivers']), 'caller')->for($me, 'callee')->create();
    $this->actingAs($me);

    visit(route('invites.index'))
        ->assertSee('Waiting for your answer')
        ->assertScript("Alpine.store('invites').pendingCount", 1)
        ->click('[aria-label="Accept the invite from Sam Rivers"]')
        ->assertSee('Upcoming calls')
        ->assertNoJavaScriptErrors();

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Accepted);
});

it('only enables Start scheduled call during the window', function (string $startsIn, bool $isEnabled): void {
    $me = User::factory()->create();
    $sam = User::factory()->create(['name' => 'Sam Rivers']);
    CallSchedule::factory()->accepted()->for($me, 'caller')->for($sam, 'callee')->create(['starts_at' => now()->modify($startsIn)]);
    $this->actingAs($me);

    $page = visit(route('invites.index'))->assertSee('Start scheduled call');
    $page->script("() => { Alpine.store('presence').onlineIds = [{$sam->id}] }");

    $page->assertScript("[...document.querySelectorAll('button')].find((button) => button.textContent.includes('Start scheduled call')).disabled", ! $isEnabled)
        ->assertNoJavaScriptErrors();
})->with([
    'in the window' => ['+2 minutes', true],
    'later on' => ['+2 hours', false],
]);
