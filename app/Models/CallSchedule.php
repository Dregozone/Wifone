<?php

namespace App\Models;

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Events\InvitesUpdated;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CallScheduleFactory;
use Generator;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agreed (or proposed) time for two people to call, once or on a repeat.
 *
 * `starts_at` is the first occurrence in UTC; repeats are worked out in the inviter's
 * `timezone`, so a weekly 19:00 slot stays at 19:00 local time across clock changes.
 * Occurrences are identified by their local date, which is what a skip records.
 */
class CallSchedule extends Model
{
    /** @use HasFactory<CallScheduleFactory> */
    use HasFactory;

    /**
     * A scheduled call can be started from this many minutes before an occurrence...
     */
    public const WINDOW_OPENS_MINUTES_BEFORE = 5;

    /**
     * ...until this many minutes after it.
     */
    public const WINDOW_CLOSES_MINUTES_AFTER = 30;

    /**
     * How far ahead to look for an occurrence before giving up (e.g. a run of skipped weeks).
     */
    private const DAYS_SEARCHED = 800;

    protected $fillable = [
        'caller_id',
        'callee_id',
        'starts_at',
        'timezone',
        'repeat',
        'weekdays',
        'until',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'repeat' => CallScheduleRepeat::class,
            'weekdays' => 'array',
            'until' => 'immutable_date',
            'status' => CallScheduleStatus::class,
        ];
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function skips(): HasMany
    {
        return $this->hasMany(CallScheduleSkip::class, 'schedule_id');
    }

    /**
     * Limit the query to schedules the given user sent or was invited to.
     *
     * @param  Builder<CallSchedule>  $query
     */
    #[Scope]
    protected function involving(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('caller_id', $user->id)
            ->orWhere('callee_id', $user->id));
    }

    /**
     * The number of invites waiting for the user's answer that can still happen.
     */
    public static function pendingInviteCountFor(User $user): int
    {
        return static::query()
            ->where('callee_id', $user->id)
            ->where('status', CallScheduleStatus::Pending)
            ->with('skips')
            ->get()
            ->filter(fn (CallSchedule $schedule): bool => $schedule->nextOccurrence() !== null)
            ->count();
    }

    /**
     * The first occurrence, set in the schedule's time zone, for the given weekdays.
     *
     * For a "several weekdays" repeat the first occurrence is the first chosen weekday on or
     * after the picked date; for the other repeats it is the picked date itself.
     *
     * @param  array<int, int>  $weekdays  ISO weekdays, 1 (Monday) to 7 (Sunday).
     */
    public static function firstOccurrenceFor(string $date, string $time, string $timezone, CallScheduleRepeat $repeat, array $weekdays = []): CarbonImmutable
    {
        $day = self::calendarDate($date);

        if ($repeat === CallScheduleRepeat::Weekdays && $weekdays !== []) {
            while (! in_array($day->isoWeekday(), $weekdays, true)) {
                $day = $day->addDay();
            }
        }

        return CarbonImmutable::parse("{$day->toDateString()} {$time}", $timezone)->utc();
    }

    /**
     * The occurrence whose window has not closed yet (in progress or upcoming), skipping
     * skipped dates, or null once the schedule has run its course.
     */
    public function nextOccurrence(?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());
        $windowStillOpenFrom = $now->subMinutes(self::WINDOW_CLOSES_MINUTES_AFTER);
        $skippedDates = $this->skippedDates();

        foreach ($this->occurrencesFrom($windowStillOpenFrom->setTimezone($this->timezone)->toDateString()) as $date => $occurrence) {
            if ($occurrence->lessThan($windowStillOpenFrom) || in_array($date, $skippedDates, true)) {
                continue;
            }

            return $occurrence;
        }

        return null;
    }

    /**
     * The occurrence whose call window is open right now, if any.
     */
    public function currentOccurrence(?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());
        $next = $this->nextOccurrence($now);

        if ($next === null || $now->lessThan($next->subMinutes(self::WINDOW_OPENS_MINUTES_BEFORE))) {
            return null;
        }

        return $next;
    }

    /**
     * Whether an agreed occurrence is open for calling right now.
     */
    public function canStartNow(?CarbonInterface $now = null): bool
    {
        return $this->status === CallScheduleStatus::Accepted && $this->currentOccurrence($now) !== null;
    }

    /**
     * Whether the schedule has an occurrence on the given local date (skipped or not).
     */
    public function occursOn(string $date): bool
    {
        foreach ($this->occurrencesFrom($date) as $occurrenceDate => $occurrence) {
            return $occurrenceDate === $date;
        }

        return false;
    }

    /**
     * Whether the schedule repeats, so a single occurrence can be skipped.
     */
    public function repeats(): bool
    {
        return $this->repeat !== CallScheduleRepeat::None;
    }

    /**
     * Whether the given user sent the invite or was invited.
     */
    public function hasParticipant(User $user): bool
    {
        return in_array($user->id, [$this->caller_id, $this->callee_id], true);
    }

    /**
     * Get the ID of the participant on the other end of the schedule from the given user.
     */
    public function otherParticipantId(User $user): int
    {
        return $user->id === $this->caller_id ? $this->callee_id : $this->caller_id;
    }

    /**
     * Move the schedule on from one status to another, but only if it still has that status.
     *
     * @param  array<int, CallScheduleStatus>  $from
     */
    public function transitionFrom(array $from, CallScheduleStatus $to): bool
    {
        $changed = static::query()
            ->whereKey($this->getKey())
            ->whereIn('status', $from)
            ->update(['status' => $to]) === 1;

        $this->refresh();

        return $changed;
    }

    /**
     * Tell both participants' browsers that their invites changed, so badges and lists update.
     *
     * Reported rather than thrown if Reverb can't be reached: the change is already saved and
     * shows on the next page load.
     */
    public function notifyParticipants(): void
    {
        foreach ([$this->caller, $this->callee] as $participant) {
            try {
                broadcast(new InvitesUpdated($participant->id, static::pendingInviteCountFor($participant)));
            } catch (BroadcastException|GuzzleException $exception) {
                report($exception);
            }
        }
    }

    /**
     * Occurrences on or after the given local date, keyed by their local date, in UTC.
     *
     * Dates are walked as plain calendar days (so clock changes can't shift the count of days
     * since the first occurrence) and each one is placed at the first occurrence's local time.
     *
     * @return Generator<string, CarbonImmutable>
     */
    private function occurrencesFrom(string $fromDate): Generator
    {
        $firstLocal = $this->starts_at->setTimezone($this->timezone);
        $firstDate = self::calendarDate($firstLocal->toDateString());
        $time = $firstLocal->format('H:i:s');
        $day = self::calendarDate(max($fromDate, $firstDate->toDateString()));
        $weekdays = array_map('intval', $this->weekdays ?? []);

        for ($daysSearched = 0; $daysSearched < self::DAYS_SEARCHED; $daysSearched++, $day = $day->addDay()) {
            if ($this->until !== null && $day->toDateString() > $this->until->toDateString()) {
                return;
            }

            $daysSinceFirst = (int) $firstDate->diffInDays($day);

            $isOccurrence = match ($this->repeat) {
                CallScheduleRepeat::None => $daysSinceFirst === 0,
                CallScheduleRepeat::Weekly => $daysSinceFirst % 7 === 0,
                CallScheduleRepeat::Fortnightly => $daysSinceFirst % 14 === 0,
                CallScheduleRepeat::Weekdays => in_array($day->isoWeekday(), $weekdays, true),
            };

            if ($isOccurrence) {
                yield $day->toDateString() => CarbonImmutable::parse("{$day->toDateString()} {$time}", $this->timezone)->utc();
            }

            if ($this->repeat === CallScheduleRepeat::None && $daysSinceFirst >= 0) {
                return;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function skippedDates(): array
    {
        return $this->skips->map(fn (CallScheduleSkip $skip): string => $skip->occurrence_date->toDateString())->all();
    }

    /**
     * A calendar date as midnight UTC, for counting whole days between dates.
     */
    private static function calendarDate(string $date): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
    }
}
