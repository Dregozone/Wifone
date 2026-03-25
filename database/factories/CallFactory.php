<?php

namespace Database\Factories;

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
            'started_at' => $this->faker->optional()->dateTimeBetween('-1 hour', 'now'),
            'ended_at' => $this->faker->optional()->dateTimeBetween('-30 minutes', 'now'),
            'status' => $this->faker->randomElement(['pending', 'completed', 'rejected', 'missed']),
        ];
    }
}
