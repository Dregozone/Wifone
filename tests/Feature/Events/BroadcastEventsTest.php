<?php

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Models\Call;

it('sends CallInitiated to the receiver with the caller details', function () {
    $call = Call::factory()->create();
    $event = new CallInitiated($call);

    expect($event->broadcastOn()[0]->name)->toBe("private-calls.{$call->receiver_id}")
        ->and($event->broadcastAs())->toBe('call.initiated')
        ->and($event->broadcastWith())->toBe([
            'callId' => $call->id,
            'callerId' => $call->caller_id,
            'callerName' => $call->caller->name,
        ]);
});

it('sends CallAccepted and CallRejected to the caller and the receiver\'s other devices', function (string $eventClass, string $name) {
    $call = Call::factory()->create();
    $event = new $eventClass($call);

    expect(collect($event->broadcastOn())->pluck('name')->all())->toBe([
        "private-calls.{$call->caller_id}",
        "private-calls.{$call->receiver_id}",
    ])
        ->and($event->broadcastAs())->toBe($name)
        ->and($event->broadcastWith())->toBe(['callId' => $call->id]);
})->with([
    [CallAccepted::class, 'call.accepted'],
    [CallRejected::class, 'call.rejected'],
]);

it('sends CallEnded to the given recipient with the final status', function () {
    $call = Call::factory()->completed()->create();
    $event = new CallEnded($call, $call->caller_id);

    expect($event->broadcastOn()[0]->name)->toBe("private-calls.{$call->caller_id}")
        ->and($event->broadcastAs())->toBe('call.ended')
        ->and($event->broadcastWith())->toBe(['callId' => $call->id, 'status' => 'completed']);
});
