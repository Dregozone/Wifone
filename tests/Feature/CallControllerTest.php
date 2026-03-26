<?php

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Events\WebRTCAnswer;
use App\Events\WebRTCIceCandidate;
use App\Events\WebRTCOffer;
use App\Models\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake();
});

it('initiates a call and broadcasts CallInitiated', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.initiate'), ['to_user_id' => $receiver->id])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(CallInitiated::class, function (CallInitiated $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('accepts a call and broadcasts CallAccepted', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.accept'), ['to_user_id' => $receiver->id])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(CallAccepted::class, function (CallAccepted $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('rejects a call and broadcasts CallRejected', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.reject'), ['to_user_id' => $receiver->id])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(CallRejected::class, function (CallRejected $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('ends a call and broadcasts CallEnded', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.end'), ['to_user_id' => $receiver->id])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(CallEnded::class, function (CallEnded $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('sends an offer and broadcasts WebRTCOffer', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.offer'), [
            'to_user_id' => $receiver->id,
            'offer' => ['type' => 'offer', 'sdp' => 'v=0'],
        ])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(WebRTCOffer::class, function (WebRTCOffer $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('sends an answer and broadcasts WebRTCAnswer', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.answer'), [
            'to_user_id' => $receiver->id,
            'answer' => ['type' => 'answer', 'sdp' => 'v=0'],
        ])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(WebRTCAnswer::class, function (WebRTCAnswer $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('sends a candidate and broadcasts WebRTCIceCandidate', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.candidate'), [
            'to_user_id' => $receiver->id,
            'candidate' => ['candidate' => 'candidate:0 1 UDP 2122252543'],
        ])
        ->assertSuccessful()
        ->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(WebRTCIceCandidate::class, function (WebRTCIceCandidate $event) use ($sender, $receiver): bool {
        return $event->toUserId === $receiver->id && $event->fromUserId === $sender->id;
    });
});

it('returns 422 when to_user_id is missing', function (string $route): void {
    $sender = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route($route), [])
        ->assertUnprocessable();
})->with([
    'call.initiate',
    'call.accept',
    'call.reject',
    'call.end',
]);

it('returns 422 when to_user_id does not exist', function (string $route): void {
    $sender = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route($route), ['to_user_id' => 99999])
        ->assertUnprocessable();
})->with([
    'call.initiate',
    'call.accept',
    'call.reject',
    'call.end',
]);

it('returns 422 when offer payload is missing', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.offer'), ['to_user_id' => $receiver->id])
        ->assertUnprocessable();
});

it('returns 422 when answer payload is missing', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.answer'), ['to_user_id' => $receiver->id])
        ->assertUnprocessable();
});

it('returns 422 when candidate payload is missing', function (): void {
    $sender = User::factory()->create();
    $receiver = User::factory()->create();

    $this->actingAs($sender)
        ->postJson(route('call.candidate'), ['to_user_id' => $receiver->id])
        ->assertUnprocessable();
});

it('redirects unauthenticated requests', function (string $route): void {
    $this->postJson(route($route), ['to_user_id' => 1])
        ->assertUnauthorized();
})->with([
    'call.initiate',
    'call.accept',
    'call.reject',
    'call.end',
    'call.offer',
    'call.answer',
    'call.candidate',
]);
