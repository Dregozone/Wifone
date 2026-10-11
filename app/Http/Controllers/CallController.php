<?php

namespace App\Http\Controllers;

use App\CallStatus;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Models\Call;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Call lifecycle only. The WebRTC offer/answer/ICE exchange happens
 * browser-to-browser via Echo whispers on the private `call.{id}` channel.
 *
 * Every status change is a conditional update (Call::transitionFrom), so when two
 * requests race for the same call exactly one wins and the other gets a 409.
 */
class CallController extends Controller
{
    /**
     * Current state of a call, used by a participant's tab to resume after a page reload.
     */
    public function show(Request $request, Call $call): JsonResponse
    {
        Gate::authorize('view', $call);

        Call::expireStale();
        $call->refresh()->load(['caller:id,name', 'receiver:id,name']);

        $isCaller = $call->caller_id === $request->user()->id;
        $peer = $isCaller ? $call->receiver : $call->caller;

        return response()->json([
            'callId' => $call->id,
            'status' => $call->status->value,
            'isCaller' => $isCaller,
            'peerId' => $peer->id,
            'peerName' => $peer->name,
            'startedAt' => $call->started_at?->toIso8601String(),
        ]);
    }

    /**
     * Keep-alive from a browser that is in the call, so abandoned calls can be expired.
     */
    public function heartbeat(Call $call): JsonResponse
    {
        Gate::authorize('signal', $call);

        $call->update(['last_heartbeat_at' => now()]);

        return response()->json(['status' => $call->status->value]);
    }

    /**
     * Ring another user. Refused while either side is already in a call; a call to a busy
     * receiver is still logged as missed so it shows in their Recents.
     */
    public function store(Request $request): JsonResponse
    {
        Call::expireStale();

        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$request->user()->id],
        ]);

        if (Call::isUserBusy($request->user())) {
            return response()->json(['message' => __('You are already in a call.')], 409);
        }

        $receiver = User::findOrFail($validated['receiver_id']);

        if (Call::isUserBusy($receiver)) {
            Call::create([
                'caller_id' => $request->user()->id,
                'receiver_id' => $receiver->id,
                'status' => CallStatus::Missed,
                'ended_at' => now(),
            ]);

            return response()->json(['message' => __(':name is on another call.', ['name' => $receiver->name])], 409);
        }

        $call = Call::create([
            'caller_id' => $request->user()->id,
            'receiver_id' => $receiver->id,
            'status' => CallStatus::Ringing,
        ]);

        if (! $this->broadcastSafely(new CallInitiated($call))) {
            // Nobody can be rung, so don't leave the call ringing until it expires.
            $call->transitionFrom(CallStatus::Ringing, ['status' => CallStatus::Missed, 'ended_at' => now()]);

            return response()->json(['message' => __('Calling is unavailable right now. Please try again in a moment.')], 503);
        }

        return response()->json(['callId' => $call->id], 201);
    }

    public function accept(Call $call): JsonResponse
    {
        Gate::authorize('accept', $call);

        $accepted = $call->transitionFrom(CallStatus::Ringing, [
            'status' => CallStatus::Active,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        if (! $accepted) {
            return $this->noLongerAvailable();
        }

        // toOthers(): the receiver's other devices also get this, so they stop ringing.
        $this->broadcastSafely(new CallAccepted($call), toOthers: true);

        return response()->json(['status' => 'ok']);
    }

    public function reject(Call $call): JsonResponse
    {
        Gate::authorize('reject', $call);

        $rejected = $call->transitionFrom(CallStatus::Ringing, [
            'status' => CallStatus::Rejected,
            'ended_at' => now(),
        ]);

        if (! $rejected) {
            return $this->noLongerAvailable();
        }

        $this->broadcastSafely(new CallRejected($call), toOthers: true);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Hang up an active call, or cancel one that is still ringing (recorded as missed).
     * Ending an already-finished call is a no-op so both sides can safely hang up at once.
     */
    public function end(Request $request, Call $call): JsonResponse
    {
        Gate::authorize('end', $call);

        if ($call->finish()) {
            $this->broadcastSafely(new CallEnded($call, $call->otherParticipantId($request->user())));
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Broadcast an event, reporting rather than throwing if Reverb can't be reached (a refused
     * connection surfaces as a Guzzle exception, an error reply as a BroadcastException).
     *
     * The call's new status is already saved, and the other side catches up through its
     * heartbeat and presence, so a failed broadcast must not turn the request into an error.
     */
    private function broadcastSafely(ShouldBroadcast $event, bool $toOthers = false): bool
    {
        try {
            $pending = broadcast($event);

            if ($toOthers) {
                $pending->toOthers();
            }

            // The event is sent when the pending broadcast is destroyed, so do that inside the try.
            unset($pending);
        } catch (BroadcastException|GuzzleException $exception) {
            report($exception);

            return false;
        }

        return true;
    }

    private function noLongerAvailable(): JsonResponse
    {
        return response()->json(['message' => __('The call is no longer available.')], 409);
    }
}
