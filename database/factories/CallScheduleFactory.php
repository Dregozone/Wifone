<?php

namespace Database\Factories;

use App\CallScheduleRepeat;
use App\CallScheduleStatus;
use App\Models\CallSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CallSchedule>
 */
class CallScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caller_id' => User::factory(),
            'callee_id' => User::factory(),
            'starts_at' => now()->addDay()->startOfHour(),
            'timezone' => 'UTC',
            'repeat' => CallScheduleRepeat::None,
            'weekdays' => null,
            'until' => null,
            'status' => CallScheduleStatus::Pending,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['status' => CallScheduleStatus::Accepted]);
    }

    public function weekly(): static
    {
        return $this->state(fn (): array => ['repeat' => CallScheduleRepeat::Weekly]);
    }

    /**
     * @param  array<int, int>  $weekdays  ISO weekdays, 1 (Monday) to 7 (Sunday).
     */
    public function onWeekdays(array $weekdays): static
    {
        return $this->state(fn (): array => ['repeat' => CallScheduleRepeat::Weekdays, 'weekdays' => $weekdays]);
    }
}
