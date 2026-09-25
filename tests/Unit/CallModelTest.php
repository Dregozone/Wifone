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
