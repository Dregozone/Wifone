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

it('changes status only when the call still has the expected status', function () {
    $call = Call::factory()->create();
    Call::whereKey($call->id)->update(['status' => CallStatus::Missed]);

    expect($call->transitionFrom(CallStatus::Ringing, ['status' => CallStatus::Active]))->toBeFalse()
        ->and($call->status)->toBe(CallStatus::Missed)
        ->and($call->fresh()->status)->toBe(CallStatus::Missed);
});

it('finishes a ringing call as missed and an active one as completed, once', function (Call $call, CallStatus $expected) {

    expect($call->finish())->toBeTrue()
        ->and($call->status)->toBe($expected)
        ->and($call->ended_at)->not->toBeNull()
        ->and($call->finish())->toBeFalse();
})->with([
    'ringing' => fn (): array => [Call::factory()->create(), CallStatus::Missed],
    'active' => fn (): array => [Call::factory()->active()->create(), CallStatus::Completed],
]);

it('treats only ringing and active calls as busy', function () {
    $ringing = Call::factory()->create();
    $finished = Call::factory()->completed()->create();

    expect(Call::isUserBusy($ringing->caller))->toBeTrue()
        ->and(Call::isUserBusy($ringing->receiver))->toBeTrue()
        ->and(Call::isUserBusy($finished->caller))->toBeFalse()
        ->and(Call::isUserBusy(User::factory()->create()))->toBeFalse();
});
