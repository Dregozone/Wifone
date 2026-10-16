<?php

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Events\InvitesUpdated;
use App\Livewire\ScheduleCall;
use App\Models\CallSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function (): void {
    Event::fake([InvitesUpdated::class]);
    $this->travelTo(now()->parse('2026-10-16 12:00', 'UTC'));
});

it('shows a Schedule a call button on every contact card', function () {
    $sam = User::factory()->create(['name' => 'Sam Rivers']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire(ScheduleCall::class)
        ->assertSee('Schedule a call with Sam Rivers');
});

it('sends a pending one-off invite in the inviter\'s time zone', function () {
    $me = User::factory()->create();
    $sam = User::factory()->create(['name' => 'Sam Rivers']);

    Livewire::actingAs($me)
        ->test(ScheduleCall::class)
        ->call('open', $sam->id)
        ->assertSet('calleeName', 'Sam Rivers')
        ->set('timezone', 'Europe/London')
        ->set('date', '2026-10-20')
        ->set('time', '19:00')
        ->call('send')
        ->assertHasNoErrors()
        ->assertDispatched('call-scheduled', message: 'Invite sent to Sam Rivers.');

    $schedule = CallSchedule::sole();

    expect($schedule)
        ->caller_id->toBe($me->id)
        ->callee_id->toBe($sam->id)
        ->status->toBe(CallScheduleStatus::Pending)
        ->repeat->toBe(CallScheduleRepeat::None)
        ->timezone->toBe('Europe/London')
        ->until->toBeNull()
        ->and($schedule->starts_at->toDateTimeString())->toBe('2026-10-20 18:00:00');

    Event::assertDispatched(InvitesUpdated::class, fn (InvitesUpdated $event): bool => $event->userId === $sam->id && $event->pendingCount === 1);
    Event::assertDispatched(InvitesUpdated::class, fn (InvitesUpdated $event): bool => $event->userId === $me->id && $event->pendingCount === 0);
});

it('sends a several-weekdays invite with an end date', function () {
    $sam = User::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(ScheduleCall::class)
        ->call('open', $sam->id)
        ->set('date', '2026-10-20')
        ->set('time', '19:00')
        ->set('repeat', 'weekdays')
        ->set('weekdays', ['4', '1'])
        ->set('until', '2026-12-31')
        ->call('send')
        ->assertHasNoErrors();

    $schedule = CallSchedule::sole();

    // Tuesday the 20th was picked, so the first call is on Thursday the 22nd.
    expect($schedule->weekdays)->toBe([1, 4])
        ->and($schedule->until->toDateString())->toBe('2026-12-31')
        ->and($schedule->starts_at->toDateTimeString())->toBe('2026-10-22 19:00:00');
});

it('validates the invite', function (array $fields, string $errorOn) {
    Livewire::actingAs(User::factory()->create())
        ->test(ScheduleCall::class)
        ->call('open', User::factory()->create()->id)
        ->set(['date' => '2026-10-20', 'time' => '19:00', ...$fields])
        ->call('send')
        ->assertHasErrors($errorOn);

    expect(CallSchedule::count())->toBe(0);
})->with([
    'no date' => [['date' => ''], 'date'],
    'in the past' => [['date' => '2026-10-15'], 'date'],
    'unknown repeat' => [['repeat' => 'daily'], 'repeat'],
    'weekdays without any' => [['repeat' => 'weekdays', 'weekdays' => []], 'weekdays'],
    'end before the start' => [['repeat' => 'weekly', 'until' => '2026-10-19'], 'until'],
    'unknown time zone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
]);

it('cannot schedule a call with yourself', function () {
    $me = User::factory()->create();

    Livewire::actingAs($me)
        ->test(ScheduleCall::class)
        ->call('open', $me->id)
        ->assertNotFound();
});
