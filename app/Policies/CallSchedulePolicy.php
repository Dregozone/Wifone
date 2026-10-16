<?php

namespace App\Policies;

use App\CallScheduleStatus;
use App\Models\CallSchedule;
use App\Models\User;

class CallSchedulePolicy
{
    /**
     * Only the invitee may accept an invite that is still waiting for an answer.
     */
    public function accept(User $user, CallSchedule $schedule): bool
    {
        return $user->id === $schedule->callee_id && $schedule->status === CallScheduleStatus::Pending;
    }

    /**
     * Only the invitee may decline an invite that is still waiting for an answer.
     */
    public function decline(User $user, CallSchedule $schedule): bool
    {
        return $this->accept($user, $schedule);
    }

    /**
     * Either participant may cancel the whole series (the inviter withdrawing it included).
     */
    public function cancel(User $user, CallSchedule $schedule): bool
    {
        return $schedule->hasParticipant($user) && $schedule->status->isOpen();
    }

    /**
     * Either participant may skip a single occurrence of a repeating schedule.
     */
    public function skip(User $user, CallSchedule $schedule): bool
    {
        return $this->cancel($user, $schedule) && $schedule->repeats();
    }

    /**
     * Either participant may start the call while an agreed occurrence's window is open.
     */
    public function start(User $user, CallSchedule $schedule): bool
    {
        return $schedule->hasParticipant($user) && $schedule->canStartNow();
    }
}
