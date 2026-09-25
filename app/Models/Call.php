<?php

namespace App\Models;

use App\CallStatus;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory;

    /**
     * A ringing call nobody answered or cancelled within this many seconds is recorded as missed.
     */
    public const RING_EXPIRY_SECONDS = 45;

    /**
     * An active call with no heartbeat from either browser for this many seconds is recorded as completed.
     */
    public const HEARTBEAT_EXPIRY_SECONDS = 60;

    protected $fillable = [
        'caller_id',
        'receiver_id',
        'started_at',
        'ended_at',
        'last_heartbeat_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
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
     * Limit the query to calls the given user made or received.
     *
     * @param  Builder<Call>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('caller_id', $user->id)
            ->orWhere('receiver_id', $user->id));
    }

    /**
     * Close off calls whose browsers disappeared without hanging up (e.g. both crashed).
     *
     * Ringing calls past the ring window become missed; active calls without a recent
     * heartbeat become completed, ending at their last sign of life.
     *
     * @return int The number of calls expired.
     */
    public static function expireStale(): int
    {
        $expiredRinging = static::query()
            ->where('status', CallStatus::Ringing)
            ->where('created_at', '<', now()->subSeconds(self::RING_EXPIRY_SECONDS))
            ->update(['status' => CallStatus::Missed, 'ended_at' => now()]);

        $staleActive = static::query()
            ->where('status', CallStatus::Active)
            ->whereRaw('coalesce(last_heartbeat_at, started_at, created_at) < ?', [now()->subSeconds(self::HEARTBEAT_EXPIRY_SECONDS)])
            ->get();

        foreach ($staleActive as $call) {
            $call->update([
                'status' => CallStatus::Completed,
                'ended_at' => $call->last_heartbeat_at ?? $call->started_at ?? $call->created_at,
            ]);
        }

        return $expiredRinging + $staleActive->count();
    }

    /**
     * Length of the connected part of the call, if it was answered and has ended.
     */
    public function durationInSeconds(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->ended_at);
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
