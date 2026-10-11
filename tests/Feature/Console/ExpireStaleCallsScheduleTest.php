<?php

use App\CallStatus;
use App\Models\Call;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('expires abandoned calls every minute from the scheduler', function (): void {
    $abandoned = Call::factory()->create(['created_at' => now()->subSeconds(Call::RING_EXPIRY_SECONDS + 1)]);

    $task = collect(app(Schedule::class)->events())
        ->sole(fn (Event $event): bool => $event->description === 'calls:expire-stale');

    expect($task->expression)->toBe('* * * * *');

    $task->run(app());

    expect($abandoned->fresh()->status)->toBe(CallStatus::Missed);
});
