<?php

namespace App\Models;

use App\CallStatus;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    protected $fillable = [
        'caller_id',
        'receiver_id',
        'started_at',
        'ended_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => CallStatus::class,
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Whether the given user is the caller or the receiver of this call.
     */
    public function hasParticipant(User $user): bool
    {
        return in_array($user->id, [$this->caller_id, $this->receiver_id], true);
    }

    /**
     * Get the ID of the participant on the other end of the call from the given user.
     */
    public function otherParticipantId(User $user): int
    {
        return $user->id === $this->caller_id ? $this->receiver_id : $this->caller_id;
    }
}
