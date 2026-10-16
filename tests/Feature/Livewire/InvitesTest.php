<?php

use App\CallScheduleStatus;
use App\Events\InvitesUpdated;
use App\Livewire\Invites;
use App\Models\CallSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    Event::fake([InvitesUpdated::class]);
});

it('requires authentication', function () {
    $this->get(route('invites.index'))->assertRedirect(route('login'));
});

it('renders the invites page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('invites.index'))
        ->assertOk()
        ->assertSeeLivewire(Invites::class)
        ->assertSee('No scheduled calls.');
});

it('lists invites to answer, upcoming calls and sent invites', function () {
    $me = User::factory()->create();

    CallSchedule::factory()->for(User::factory()->create(['name' => 'Inviting Ivy']), 'caller')->for($me, 'callee')->create();
    CallSchedule::factory()->accepted()->for($me, 'caller')->for(User::factory()->create(['name' => 'Agreed Al']), 'callee')->create();
    CallSchedule::factory()->for($me, 'caller')->for(User::factory()->create(['name' => 'Waiting Wren']), 'callee')->create();

    Livewire::actingAs($me)
        ->test(Invites::class)
        ->assertSeeInOrder(['Waiting for your answer', 'Inviting Ivy', 'Upcoming calls', 'Agreed Al', 'Start scheduled call', 'Sent invites', 'Waiting Wren']);
});

it('hides schedules that are over, closed or between other people', function () {
    $me = User::factory()->create();

    CallSchedule::factory()->accepted()->for($me, 'callee')->for(User::factory()->create(['name' => 'Past Pat']), 'caller')->create(['starts_at' => now()->subDay()]);
    CallSchedule::factory()->for($me, 'callee')->for(User::factory()->create(['name' => 'Declined Dee']), 'caller')->create(['status' => CallScheduleStatus::Declined]);
    CallSchedule::factory()->for(User::factory()->create(['name' => 'Private Alice']), 'caller')->for(User::factory()->create(['name' => 'Private Bob']), 'callee')->create();

    Livewire::actingAs($me)
        ->test(Invites::class)
        ->assertDontSee('Past Pat')
        ->assertDontSee('Declined Dee')
        ->assertDontSee('Private Alice')
        ->assertSee('No scheduled calls.');
});

it('lets the invitee accept an invite and tells both people', function () {
    $schedule = CallSchedule::factory()->create();

    Livewire::actingAs($schedule->callee)
        ->test(Invites::class)
        ->call('accept', $schedule->id)
        ->assertSee('Upcoming calls');

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Accepted);

    Event::assertDispatched(InvitesUpdated::class, fn (InvitesUpdated $event): bool => $event->userId === $schedule->callee_id && $event->pendingCount === 0);
    Event::assertDispatched(InvitesUpdated::class, fn (InvitesUpdated $event): bool => $event->userId === $schedule->caller_id);
});

it('lets the invitee decline an invite', function () {
    $schedule = CallSchedule::factory()->create();

    Livewire::actingAs($schedule->callee)
        ->test(Invites::class)
        ->call('decline', $schedule->id)
        ->assertSee('No scheduled calls.');

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Declined);
});

it('does not let the inviter answer their own invite', function (string $action) {
    $schedule = CallSchedule::factory()->create();

    Livewire::actingAs($schedule->caller)
        ->test(Invites::class)
        ->call($action, $schedule->id)
        ->assertForbidden();

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Pending);
})->with(['accept', 'decline']);

it('does not let an invite be answered twice', function () {
    $schedule = CallSchedule::factory()->create(['status' => CallScheduleStatus::Declined]);

    Livewire::actingAs($schedule->callee)
        ->test(Invites::class)
        ->call('accept', $schedule->id)
        ->assertForbidden();

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Declined);
});

it('lets either person cancel the whole series', function (string $participant) {
    $schedule = CallSchedule::factory()->accepted()->weekly()->create();

    Livewire::actingAs($schedule->{$participant})
        ->test(Invites::class)
        ->call('cancel', $schedule->id)
        ->assertSee('No scheduled calls.');

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Cancelled);
    Event::assertDispatchedTimes(InvitesUpdated::class, 2);
})->with(['caller', 'callee']);

it('lets either person skip a single occurrence', function (string $participant) {
    $schedule = CallSchedule::factory()->accepted()->weekly()->create(['starts_at' => now()->addDay()->setTime(19, 0)]);
    $firstDate = $schedule->starts_at->toDateString();

    Livewire::actingAs($schedule->{$participant})
        ->test(Invites::class)
        ->call('skip', $schedule->id, $firstDate)
        ->assertOk();

    $schedule->refresh();

    expect($schedule->skips()->sole()->occurrence_date->toDateString())->toBe($firstDate)
        ->and($schedule->status)->toBe(CallScheduleStatus::Accepted)
        ->and($schedule->nextOccurrence()->toDateTimeString())->toBe($schedule->starts_at->addWeek()->toDateTimeString());
})->with(['caller', 'callee']);

it('only skips dates the schedule actually has', function () {
    $schedule = CallSchedule::factory()->accepted()->weekly()->create(['starts_at' => now()->addDay()->setTime(19, 0)]);

    Livewire::actingAs($schedule->caller)
        ->test(Invites::class)
        ->call('skip', $schedule->id, $schedule->starts_at->addDay()->toDateString())
        ->assertStatus(422);

    expect($schedule->skips()->count())->toBe(0);
});

it('does not skip a one-off call (cancel it instead)', function () {
    $schedule = CallSchedule::factory()->accepted()->create();

    Livewire::actingAs($schedule->caller)
        ->test(Invites::class)
        ->call('skip', $schedule->id, $schedule->starts_at->toDateString())
        ->assertForbidden();
});

it('never lets other people touch a schedule', function (string $action) {
    $schedule = CallSchedule::factory()->accepted()->weekly()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(Invites::class)
        ->call($action, $schedule->id, $schedule->starts_at->toDateString())
        ->assertNotFound();

    expect($schedule->fresh()->status)->toBe(CallScheduleStatus::Accepted);
})->with(['accept', 'decline', 'cancel', 'skip']);

it('shows the pending invite count as a badge in the navigation', function () {
    $me = User::factory()->create();
    CallSchedule::factory()->count(3)->for($me, 'callee')->create();

    $this->actingAs($me)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('window.pendingInviteCount = 3;', false)
        ->assertSee('$store.invites.pendingCount', false);
});
