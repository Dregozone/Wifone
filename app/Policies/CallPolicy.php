<?php

namespace App\Policies;

use App\CallStatus;
use App\Models\Call;
use App\Models\User;

class CallPolicy
{
    /**
     * Only the two participants may look up a call.
     */
    public function view(User $user, Call $call): bool
    {
        return $call->hasParticipant($user);
    }

    /**
     * Only the receiver may answer a call that is still ringing.
     */
    public function accept(User $user, Call $call): bool
    {
        return $user->id === $call->receiver_id && $call->status === CallStatus::Ringing;
    }

    /**
     * Only the receiver may decline a call that is still ringing.
     */
    public function reject(User $user, Call $call): bool
    {
        return $this->accept($user, $call);
    }

    /**
     * Either participant may hang up (or cancel) the call.
     */
    public function end(User $user, Call $call): bool
    {
        return $call->hasParticipant($user);
    }

    /**
     * Either participant may join the call's signalling channel while the call is live.
     */
    public function signal(User $user, Call $call): bool
    {
        return $call->hasParticipant($user) && $call->status->isLive();
    }
}
