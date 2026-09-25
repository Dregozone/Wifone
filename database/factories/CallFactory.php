<?php

namespace Database\Factories;

use App\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Call>
 */
class CallFactory extends Factory
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
            'receiver_id' => User::factory(),
            'started_at' => null,
            'ended_at' => null,
            'status' => CallStatus::Ringing,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CallStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => CallStatus::Completed,
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
    }
}
