<?php

namespace App;

enum CallScheduleRepeat: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Fortnightly = 'fortnightly';
    case Weekdays = 'weekdays';

    /**
     * A short description for the invite, e.g. "Every week".
     */
    public function label(): string
    {
        return match ($this) {
            self::None => __('One-off'),
            self::Weekly => __('Every week'),
            self::Fortnightly => __('Every other week'),
            self::Weekdays => __('Several weekdays'),
        };
    }
}
