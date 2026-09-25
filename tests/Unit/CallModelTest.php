<?php

use App\CallStatus;
use App\Models\Call;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('caller relation returns a User instance', function () {
    $call = Call::factory()->create();

    expect($call->caller)->toBeInstanceOf(User::class);
});

it('receiver relation returns a User instance', function () {
    $call = Call::factory()->create();

    expect($call->receiver)->toBeInstanceOf(User::class);
});

it('casts status to the CallStatus enum', function () {
    $call = Call::factory()->active()->create();

    expect($call->status)->toBe(CallStatus::Active)
        ->and($call->status->isLive())->toBeTrue();
});

it('identifies participants and the other side of the call', function () {
    $call = Call::factory()->create();

    expect($call->hasParticipant($call->caller))->toBeTrue()
        ->and($call->hasParticipant(User::factory()->create()))->toBeFalse()
        ->and($call->otherParticipantId($call->caller))->toBe($call->receiver_id)
        ->and($call->otherParticipantId($call->receiver))->toBe($call->caller_id);
});

it('expires ringing calls nobody answered', function () {
    $stale = Call::factory()->create(['created_at' => now()->subSeconds(Call::RING_EXPIRY_SECONDS + 5)]);
    $fresh = Call::factory()->create();

    expect(Call::expireStale())->toBe(1)
        ->and($stale->fresh()->status)->toBe(CallStatus::Missed)
        ->and($fresh->fresh()->status)->toBe(CallStatus::Ringing);
});

it('completes active calls whose heartbeat stopped, ending at the last heartbeat', function () {
    $lastSeen = now()->subSeconds(Call::HEARTBEAT_EXPIRY_SECONDS + 30)->startOfSecond();

    $abandoned = Call::factory()->active()->create([
        'started_at' => $lastSeen->copy()->subMinutes(5),
        'last_heartbeat_at' => $lastSeen,
    ]);
    $healthy = Call::factory()->active()->create(['last_heartbeat_at' => now()]);

    expect(Call::expireStale())->toBe(1);

    expect($abandoned->fresh())
        ->status->toBe(CallStatus::Completed)
        ->ended_at->equalTo($lastSeen)->toBeTrue()
        ->and($healthy->fresh()->status)->toBe(CallStatus::Active);
});
