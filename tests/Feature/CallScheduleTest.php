<?php

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Models\CallSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A schedule set in London, starting at the given local time.
 *
 * @param  array<string, mixed>  $attributes
 */
function londonSchedule(string $firstLocalTime, CallScheduleRepeat $repeat, array $attributes = []): CallSchedule
{
    return CallSchedule::factory()->accepted()->create([
        'starts_at' => CarbonImmutable::parse($firstLocalTime, 'Europe/London')->utc(),
        'timezone' => 'Europe/London',
        'repeat' => $repeat,
        ...$attributes,
    ]);
}

function nextOccurrenceInUtc(CallSchedule $schedule, string $nowInUtc): ?string
{
    return $schedule->fresh()->nextOccurrence(CarbonImmutable::parse($nowInUtc, 'UTC'))?->toDateTimeString();
}

it('has a single occurrence when it is a one-off', function (): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::None);

    expect(nextOccurrenceInUtc($schedule, '2026-10-20 12:00'))->toBe('2026-11-02 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-11-02 19:29'))->toBe('2026-11-02 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-11-02 19:31'))->toBeNull();
});

it('repeats weekly on the same weekday and time', function (): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::Weekly);

    expect(nextOccurrenceInUtc($schedule, '2026-10-20 12:00'))->toBe('2026-11-02 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-11-03 12:00'))->toBe('2026-11-09 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-12-01 12:00'))->toBe('2026-12-07 19:00:00');
});

it('keeps a weekly slot at the same local time across the October clock change', function (): void {
    // Monday 19 October is in British Summer Time (UTC+1); the clocks go back on Sunday 25 October.
    $schedule = londonSchedule('2026-10-19 19:00', CallScheduleRepeat::Weekly);

    expect($schedule->starts_at->toDateTimeString())->toBe('2026-10-19 18:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-10-21 12:00'))->toBe('2026-10-26 19:00:00');
});

it('keeps a weekly slot at the same local time across the March clock change', function (): void {
    // The clocks go forward on Sunday 28 March 2027.
    $schedule = londonSchedule('2027-03-22 19:00', CallScheduleRepeat::Weekly);

    expect($schedule->starts_at->toDateTimeString())->toBe('2027-03-22 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2027-03-24 12:00'))->toBe('2027-03-29 18:00:00');
});

it('repeats fortnightly counting from the first date', function (): void {
    $schedule = londonSchedule('2026-10-01 19:00', CallScheduleRepeat::Fortnightly);

    expect(nextOccurrenceInUtc($schedule, '2026-10-02 12:00'))->toBe('2026-10-15 18:00:00')
        // Not the 22nd: the off weeks are anchored to 1 October.
        ->and(nextOccurrenceInUtc($schedule, '2026-10-20 12:00'))->toBe('2026-10-29 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-10-30 12:00'))->toBe('2026-11-12 19:00:00');
});

it('keeps fortnightly anchoring and local time across the March clock change', function (): void {
    $schedule = londonSchedule('2027-03-18 19:00', CallScheduleRepeat::Fortnightly);

    expect(nextOccurrenceInUtc($schedule, '2027-03-19 12:00'))->toBe('2027-04-01 18:00:00');
});

it('repeats on several weekdays', function (): void {
    // Monday 2 November and Thursday 5 November, at 19:00.
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::Weekdays, ['weekdays' => [1, 4]]);

    expect(nextOccurrenceInUtc($schedule, '2026-11-03 12:00'))->toBe('2026-11-05 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-11-06 12:00'))->toBe('2026-11-09 19:00:00');
});

it('starts a several-weekdays repeat on the first chosen weekday on or after the picked date', function (): void {
    // Picked Tuesday 3 November; Mondays and Thursdays, so the first call is Thursday 5 November.
    $first = CallSchedule::firstOccurrenceFor('2026-11-03', '19:00', 'Europe/London', CallScheduleRepeat::Weekdays, [1, 4]);

    expect($first->toDateTimeString())->toBe('2026-11-05 19:00:00');
});

it('stops repeating after the end date', function (): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::Weekly, ['until' => '2026-11-09']);

    expect(nextOccurrenceInUtc($schedule, '2026-11-03 12:00'))->toBe('2026-11-09 19:00:00')
        ->and(nextOccurrenceInUtc($schedule, '2026-11-10 12:00'))->toBeNull();
});

it('passes over skipped occurrences', function (): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::Weekly);
    $schedule->skips()->create(['occurrence_date' => '2026-11-09']);

    expect(nextOccurrenceInUtc($schedule, '2026-11-03 12:00'))->toBe('2026-11-16 19:00:00')
        ->and($schedule->occursOn('2026-11-09'))->toBeTrue()
        ->and($schedule->occursOn('2026-11-10'))->toBeFalse();
});

it('opens the call window from 5 minutes before to 30 minutes after an occurrence', function (string $now, bool $isOpen): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::Weekly);

    expect($schedule->canStartNow(CarbonImmutable::parse($now, 'UTC')))->toBe($isOpen);
})->with([
    'an hour before' => ['2026-11-02 18:00', false],
    '6 minutes before' => ['2026-11-02 18:54', false],
    '5 minutes before' => ['2026-11-02 18:55', true],
    'on time' => ['2026-11-02 19:00', true],
    '30 minutes after' => ['2026-11-02 19:30', true],
    '31 minutes after' => ['2026-11-02 19:31', false],
    'next week, on time' => ['2026-11-09 19:00', true],
]);

it('only opens the call window once the invite is accepted', function (): void {
    $schedule = londonSchedule('2026-11-02 19:00', CallScheduleRepeat::None, ['status' => CallScheduleStatus::Pending]);

    expect($schedule->canStartNow(CarbonImmutable::parse('2026-11-02 19:00', 'UTC')))->toBeFalse();
});

it('counts the pending invites waiting for the user that can still happen', function (): void {
    $user = User::factory()->create();

    CallSchedule::factory()->count(2)->for($user, 'callee')->create();
    CallSchedule::factory()->for($user, 'callee')->create(['starts_at' => now()->subDay()]);
    CallSchedule::factory()->for($user, 'callee')->accepted()->create();
    CallSchedule::factory()->for($user, 'callee')->create(['status' => CallScheduleStatus::Declined]);
    CallSchedule::factory()->for($user, 'caller')->create();

    expect(CallSchedule::pendingInviteCountFor($user))->toBe(2);
});
