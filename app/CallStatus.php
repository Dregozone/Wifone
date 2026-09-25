<?php

namespace App;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case Active = 'active';
    case Rejected = 'rejected';
    case Missed = 'missed';
    case Completed = 'completed';

    /**
     * Whether the call is still in progress (ringing or connected).
     */
    public function isLive(): bool
    {
        return in_array($this, [self::Ringing, self::Active], true);
    }
}
