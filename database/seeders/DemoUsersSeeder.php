<?php

namespace Database\Seeders;

use App\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only demo accounts for trying calls between two browsers.
 * Both accounts use the password "password" (the UserFactory default).
 *
 * php artisan db:seed --class=DemoUsersSeeder
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $alex = User::firstOrCreate(['email' => 'alex@wifone.test'], User::factory()->raw(['name' => 'Alex Rivera', 'email' => 'alex@wifone.test']));
        $sam = User::firstOrCreate(['email' => 'sam@wifone.test'], User::factory()->raw(['name' => 'Sam Okafor', 'email' => 'sam@wifone.test']));

        if (Call::involving($alex)->exists()) {
            return;
        }

        $calls = [
            [$alex, $sam, CallStatus::Completed, now()->subDays(3)->setTime(18, 30), 1361],
            [$sam, $alex, CallStatus::Rejected, now()->subDay()->setTime(9, 15), null],
            [$alex, $sam, CallStatus::Missed, now()->subDay()->setTime(20, 5), null],
            [$sam, $alex, CallStatus::Missed, now()->subHours(3), null],
            [$alex, $sam, CallStatus::Completed, now()->subHour(), 257],
        ];

        foreach ($calls as [$caller, $receiver, $status, $at, $seconds]) {
            Call::factory()->for($caller, 'caller')->for($receiver, 'receiver')->create([
                'status' => $status,
                'created_at' => $at,
                'started_at' => $seconds === null ? null : $at,
                'ended_at' => $seconds === null ? null : $at->copy()->addSeconds($seconds),
            ]);
        }
    }
}
