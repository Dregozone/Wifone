<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single occurrence of a repeating schedule that won't happen, identified by its local date.
 */
class CallScheduleSkip extends Model
{
    protected $fillable = [
        'schedule_id',
        'occurrence_date',
    ];

    protected function casts(): array
    {
        return [
            'occurrence_date' => 'immutable_date',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CallSchedule::class, 'schedule_id');
    }
}
