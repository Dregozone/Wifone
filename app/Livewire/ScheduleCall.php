<?php

namespace App\Livewire;

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Models\CallSchedule;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The "Schedule a call" form, opened from a contact's card. The invite is stored as pending
 * until the other person accepts or declines it.
 */
class ScheduleCall extends Component
{
    public ?int $calleeId = null;

    public string $calleeName = '';

    public string $date = '';

    public string $time = '';

    public string $repeat = 'none';

    /** @var array<int, int|string> ISO weekdays, 1 (Monday) to 7 (Sunday). */
    public array $weekdays = [];

    public string $until = '';

    /** The inviter's time zone, filled in from their browser. */
    public string $timezone = 'UTC';

    /**
     * Open the form for the given contact (dispatched from their card).
     */
    #[On('schedule-call')]
    public function open(int $userId): void
    {
        $callee = User::whereKeyNot(Auth::id())->findOrFail($userId);

        $this->resetValidation();
        $this->reset('date', 'time', 'repeat', 'weekdays', 'until');
        $this->calleeId = $callee->id;
        $this->calleeName = $callee->name;

        Flux::modal('schedule-call')->show();
    }

    public function send(): void
    {
        $validated = $this->validate([
            'calleeId' => ['required', 'integer', Rule::exists('users', 'id'), Rule::notIn([Auth::id()])],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'repeat' => ['required', Rule::enum(CallScheduleRepeat::class)],
            'weekdays' => ['exclude_unless:repeat,weekdays', 'required', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'until' => ['exclude_if:repeat,none', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date'],
            'timezone' => ['required', 'timezone:all'],
        ], [
            'weekdays.required' => __('Pick at least one weekday.'),
        ], [
            'until' => __('end date'),
        ]);

        $repeat = CallScheduleRepeat::from($validated['repeat']);
        $weekdays = $repeat === CallScheduleRepeat::Weekdays
            ? collect($validated['weekdays'])->map(fn (int|string $weekday): int => (int) $weekday)->unique()->sort()->values()->all()
            : null;

        $schedule = new CallSchedule([
            'caller_id' => Auth::id(),
            'callee_id' => $validated['calleeId'],
            'starts_at' => CallSchedule::firstOccurrenceFor($validated['date'], $validated['time'], $validated['timezone'], $repeat, $weekdays ?? []),
            'timezone' => $validated['timezone'],
            'repeat' => $repeat,
            'weekdays' => $weekdays,
            'until' => $repeat === CallScheduleRepeat::None || blank($validated['until'] ?? null) ? null : $validated['until'],
            'status' => CallScheduleStatus::Pending,
        ]);

        if ($schedule->starts_at->isPast()) {
            $this->addError('date', __('Pick a time in the future.'));

            return;
        }

        if ($schedule->nextOccurrence() === null) {
            $this->addError('until', __('No calls would happen before the end date.'));

            return;
        }

        $schedule->save();
        $schedule->notifyParticipants();

        Flux::modal('schedule-call')->close();
        $this->dispatch('call-scheduled', message: __('Invite sent to :name.', ['name' => $this->calleeName]));
    }

    public function render(): View
    {
        return view('livewire.schedule-call', [
            'repeats' => CallScheduleRepeat::cases(),
        ]);
    }
}
