<?php

use App\CallStatus;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Models\Call;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

it('starts a ringing call and notifies the receiver', function (): void {
    $caller = User::factory()->create();
    $receiver = User::factory()->create();

    $response = $this->actingAs($caller)
        ->postJson(route('calls.store'), ['receiver_id' => $receiver->id])
        ->assertCreated();

    $call = Call::findOrFail($response->json('callId'));

    expect($call->caller_id)->toBe($caller->id)
        ->and($call->receiver_id)->toBe($receiver->id)
        ->and($call->status)->toBe(CallStatus::Ringing);

    Event::assertDispatched(CallInitiated::class, fn (CallInitiated $event): bool => $event->call->is($call));
});

it('validates the receiver when starting a call', function (mixed $receiverId): void {
    $caller = User::factory()->create();

    $this->actingAs($caller)
        ->postJson(route('calls.store'), ['receiver_id' => $receiverId === 'self' ? $caller->id : $receiverId])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receiver_id');

    Event::assertNotDispatched(CallInitiated::class);
})->with([
    'missing' => [null],
    'unknown user' => [999999],
    'yourself' => ['self'],
]);

it('lets the receiver accept a ringing call', function (): void {
    $call = Call::factory()->create();

    $this->actingAs($call->receiver)
        ->postJson(route('calls.accept', $call))
        ->assertOk();

    expect($call->fresh())
        ->status->toBe(CallStatus::Active)
        ->started_at->not->toBeNull();

    Event::assertDispatched(CallAccepted::class, fn (CallAccepted $event): bool => $event->call->is($call));
});

it('lets the receiver reject a ringing call', function (): void {
    $call = Call::factory()->create();

    $this->actingAs($call->receiver)
        ->postJson(route('calls.reject', $call))
        ->assertOk();

    expect($call->fresh()->status)->toBe(CallStatus::Rejected);

    Event::assertDispatched(CallRejected::class, fn (CallRejected $event): bool => $event->call->is($call));
});

it('does not let the caller or a stranger accept or reject', function (string $routeName): void {
    $call = Call::factory()->create();

    $this->actingAs($call->caller)->postJson(route($routeName, $call))->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson(route($routeName, $call))->assertForbidden();

    expect($call->fresh()->status)->toBe(CallStatus::Ringing);
})->with(['calls.accept', 'calls.reject']);

it('cannot accept a call that is no longer ringing', function (): void {
    $call = Call::factory()->completed()->create();

    $this->actingAs($call->receiver)
        ->postJson(route('calls.accept', $call))
        ->assertForbidden();
});

it('completes an active call and notifies the other participant', function (): void {
    $call = Call::factory()->active()->create();

    $this->actingAs($call->receiver)
        ->postJson(route('calls.end', $call))
        ->assertOk();

    expect($call->fresh())
        ->status->toBe(CallStatus::Completed)
        ->ended_at->not->toBeNull();

    Event::assertDispatched(CallEnded::class, fn (CallEnded $event): bool => $event->call->is($call)
        && $event->recipientId === $call->caller_id);
});

it('marks a ringing call as missed when the caller cancels', function (): void {
    $call = Call::factory()->create();

    $this->actingAs($call->caller)
        ->postJson(route('calls.end', $call))
        ->assertOk();

    expect($call->fresh()->status)->toBe(CallStatus::Missed);

    Event::assertDispatched(CallEnded::class, fn (CallEnded $event): bool => $event->recipientId === $call->receiver_id);
});

it('treats ending an already finished call as a no-op', function (): void {
    $call = Call::factory()->completed()->create();

    $this->actingAs($call->caller)
        ->postJson(route('calls.end', $call))
        ->assertOk();

    expect($call->fresh()->status)->toBe(CallStatus::Completed);

    Event::assertNotDispatched(CallEnded::class);
});

it('does not let a stranger end a call', function (): void {
    $call = Call::factory()->active()->create();

    $this->actingAs(User::factory()->create())
        ->postJson(route('calls.end', $call))
        ->assertForbidden();

    expect($call->fresh()->status)->toBe(CallStatus::Active);
});

it('requires authentication for call endpoints', function (): void {
    $call = Call::factory()->create();

    $this->postJson(route('calls.store'), ['receiver_id' => $call->receiver_id])->assertUnauthorized();
    $this->postJson(route('calls.accept', $call))->assertUnauthorized();
    $this->postJson(route('calls.reject', $call))->assertUnauthorized();
    $this->postJson(route('calls.end', $call))->assertUnauthorized();
});
