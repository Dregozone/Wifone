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

        $expiredActive = $staleActive->filter(fn (Call $call): bool => $call->transitionFrom(CallStatus::Active, [
            'status' => CallStatus::Completed,
            'ended_at' => $call->last_heartbeat_at ?? $call->started_at ?? $call->created_at,
        ]));

        return $expiredRinging + $expiredActive->count();
    }

    /**
     * Move the call on from the given status, but only if it still has that status in the database.
     *
     * Two requests can race for the same call (the receiver accepts as the caller cancels, two of the
     * receiver's devices answer at once), and each one checked the status it loaded earlier. The
     * conditional update lets exactly one of them win; the loser gets false and changes nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function transitionFrom(CallStatus $from, array $attributes): bool
    {
        $changed = static::query()
            ->whereKey($this->getKey())
            ->where('status', $from)
            ->update($attributes) === 1;

        $this->refresh();

        return $changed;
    }

    /**
     * Cancel a ringing call (missed) or hang up an active one (completed).
     *
     * Ringing is tried first: if the receiver answers in between, the second step still ends the call.
     *
     * @return bool Whether this request ended the call, rather than finding it already over.
     */
    public function finish(): bool
    {
        return $this->transitionFrom(CallStatus::Ringing, ['status' => CallStatus::Missed, 'ended_at' => now()])
            || $this->transitionFrom(CallStatus::Active, ['status' => CallStatus::Completed, 'ended_at' => now()]);
    }

    /**
     * Whether the user is in a call that is ringing or connected.
     */
    public static function isUserBusy(User $user): bool
    {
        return static::query()
            ->involving($user)
            ->whereIn('status', [CallStatus::Ringing, CallStatus::Active])
            ->exists();
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
