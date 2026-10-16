<?php

namespace App\Livewire;

use App\CallScheduleStatus;
use App\Models\CallSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The signed-in user's scheduled calls: invites waiting for their answer, invites they sent,
 * and agreed calls coming up. Only schedules they are part of are ever queried.
 */
#[Title('Invites')]
class Invites extends Component
{
    /**
     * Refresh when one of the user's schedules changes, here or in the other person's browser.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:user.'.Auth::id().',.invites.updated' => 'refreshSchedules',
        ];
    }

    /**
     * Open schedules involving the user that can still happen, soonest first, each with its
     * next occurrence.
     *
     * @return Collection<int, array{schedule: CallSchedule, next: CarbonImmutable}>
     */
    #[Computed]
    public function schedules(): Collection
    {
        return CallSchedule::query()
            ->involving(Auth::user())
            ->whereIn('status', [CallScheduleStatus::Pending, CallScheduleStatus::Accepted])
            ->with(['caller:id,name', 'callee:id,name', 'skips'])
            ->get()
            ->map(fn (CallSchedule $schedule): array => ['schedule' => $schedule, 'next' => $schedule->nextOccurrence()])
            ->filter(fn (array $entry): bool => $entry['next'] !== null)
            ->sortBy(fn (array $entry): int => $entry['next']->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, array{schedule: CallSchedule, next: CarbonImmutable}>
     */
    #[Computed]
    public function invitesToAnswer(): Collection
    {
        return $this->schedules->filter(fn (array $entry): bool => $entry['schedule']->status === CallScheduleStatus::Pending
            && $entry['schedule']->callee_id === Auth::id())->values();
    }

    /**
     * @return Collection<int, array{schedule: CallSchedule, next: CarbonImmutable}>
     */
    #[Computed]
    public function invitesSent(): Collection
    {
        return $this->schedules->filter(fn (array $entry): bool => $entry['schedule']->status === CallScheduleStatus::Pending
            && $entry['schedule']->caller_id === Auth::id())->values();
    }

    /**
     * @return Collection<int, array{schedule: CallSchedule, next: CarbonImmutable}>
     */
    #[Computed]
    public function upcomingCalls(): Collection
    {
        return $this->schedules->filter(fn (array $entry): bool => $entry['schedule']->status === CallScheduleStatus::Accepted)->values();
    }

    public function accept(int $scheduleId): void
    {
        $this->answer($scheduleId, 'accept', CallScheduleStatus::Accepted);
    }

    public function decline(int $scheduleId): void
    {
        $this->answer($scheduleId, 'decline', CallScheduleStatus::Declined);
    }

    /**
     * Cancel the whole series (or withdraw an invite that hasn't been answered).
     */
    public function cancel(int $scheduleId): void
    {
        $schedule = $this->findSchedule($scheduleId);

        Gate::authorize('cancel', $schedule);

        if ($schedule->transitionFrom([CallScheduleStatus::Pending, CallScheduleStatus::Accepted], CallScheduleStatus::Cancelled)) {
            $schedule->notifyParticipants();
        }

        $this->refreshSchedules();
    }

    /**
     * Skip a single occurrence of a repeating schedule, given by its local date.
     */
    public function skip(int $scheduleId, string $occurrenceDate): void
    {
        $schedule = $this->findSchedule($scheduleId);

        Gate::authorize('skip', $schedule);

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurrenceDate) === 1 && $schedule->occursOn($occurrenceDate), 422);

        if (! $schedule->skips()->whereDate('occurrence_date', $occurrenceDate)->exists()) {
            $schedule->skips()->create(['occurrence_date' => $occurrenceDate]);
            $schedule->notifyParticipants();
        }

        $this->refreshSchedules();
    }

    public function refreshSchedules(): void
    {
        unset($this->schedules, $this->invitesToAnswer, $this->invitesSent, $this->upcomingCalls);
    }

    public function render(): View
    {
        return view('livewire.invites');
    }

    private function answer(int $scheduleId, string $ability, CallScheduleStatus $to): void
    {
        $schedule = $this->findSchedule($scheduleId);

        Gate::authorize($ability, $schedule);

        if ($schedule->transitionFrom([CallScheduleStatus::Pending], $to)) {
            $schedule->notifyParticipants();
        }

        $this->refreshSchedules();
    }

    private function findSchedule(int $scheduleId): CallSchedule
    {
        return CallSchedule::query()->involving(Auth::user())->with('skips')->findOrFail($scheduleId);
    }
}
