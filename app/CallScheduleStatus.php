<?php

namespace App;

enum CallScheduleStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    /**
     * Whether the schedule may still lead to calls (waiting for an answer, or agreed).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], true);
    }
}
