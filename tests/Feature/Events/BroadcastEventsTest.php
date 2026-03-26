<?php

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Events\WebRTCAnswer;
use App\Events\WebRTCIceCandidate;
use App\Events\WebRTCOffer;
use Illuminate\Support\Facades\Event;

it('broadcasts CallInitiated on the correct private channel', function () {
    Event::fake();

    CallInitiated::dispatch(toUserId: 5, fromUserId: 1, fromUserName: 'Alice');

    Event::assertDispatched(CallInitiated::class, function (CallInitiated $event) {
        return $event->toUserId === 5
            && $event->fromUserId === 1
            && $event->fromUserName === 'Alice'
            && $event->broadcastOn()[0]->name === 'private-calls.5'
            && $event->broadcastAs() === 'call.initiated';
    });
});

it('broadcasts CallAccepted on the correct private channel', function () {
    Event::fake();

    CallAccepted::dispatch(toUserId: 1, fromUserId: 5);

    Event::assertDispatched(CallAccepted::class, function (CallAccepted $event) {
        return $event->toUserId === 1
            && $event->fromUserId === 5
            && $event->broadcastOn()[0]->name === 'private-calls.1'
            && $event->broadcastAs() === 'call.accepted';
    });
});

it('broadcasts CallRejected on the correct private channel', function () {
    Event::fake();

    CallRejected::dispatch(toUserId: 1, fromUserId: 5);

    Event::assertDispatched(CallRejected::class, function (CallRejected $event) {
        return $event->toUserId === 1
            && $event->fromUserId === 5
            && $event->broadcastOn()[0]->name === 'private-calls.1'
            && $event->broadcastAs() === 'call.rejected';
    });
});

it('broadcasts CallEnded on the correct private channel', function () {
    Event::fake();

    CallEnded::dispatch(toUserId: 1, fromUserId: 5);

    Event::assertDispatched(CallEnded::class, function (CallEnded $event) {
        return $event->toUserId === 1
            && $event->fromUserId === 5
            && $event->broadcastOn()[0]->name === 'private-calls.1'
            && $event->broadcastAs() === 'call.ended';
    });
});

it('broadcasts WebRTCOffer on the correct private channel', function () {
    Event::fake();

    $sdp = (object) ['type' => 'offer', 'sdp' => 'v=0...'];

    WebRTCOffer::dispatch(toUserId: 2, fromUserId: 3, offer: $sdp);

    Event::assertDispatched(WebRTCOffer::class, function (WebRTCOffer $event) use ($sdp) {
        return $event->toUserId === 2
            && $event->fromUserId === 3
            && $event->offer === $sdp
            && $event->broadcastOn()[0]->name === 'private-calls.2'
            && $event->broadcastAs() === 'webrtc.offer';
    });
});

it('broadcasts WebRTCAnswer on the correct private channel', function () {
    Event::fake();

    $sdp = (object) ['type' => 'answer', 'sdp' => 'v=0...'];

    WebRTCAnswer::dispatch(toUserId: 3, fromUserId: 2, answer: $sdp);

    Event::assertDispatched(WebRTCAnswer::class, function (WebRTCAnswer $event) use ($sdp) {
        return $event->toUserId === 3
            && $event->fromUserId === 2
            && $event->answer === $sdp
            && $event->broadcastOn()[0]->name === 'private-calls.3'
            && $event->broadcastAs() === 'webrtc.answer';
    });
});

it('broadcasts WebRTCIceCandidate on the correct private channel', function () {
    Event::fake();

    $candidate = (object) ['candidate' => 'candidate:...', 'sdpMid' => '0'];

    WebRTCIceCandidate::dispatch(toUserId: 4, fromUserId: 2, candidate: $candidate);

    Event::assertDispatched(WebRTCIceCandidate::class, function (WebRTCIceCandidate $event) use ($candidate) {
        return $event->toUserId === 4
            && $event->fromUserId === 2
            && $event->candidate === $candidate
            && $event->broadcastOn()[0]->name === 'private-calls.4'
            && $event->broadcastAs() === 'webrtc.ice-candidate';
    });
});
