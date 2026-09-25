<?php

namespace App\Http\Controllers;

use App\CallStatus;
use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Call lifecycle only. The WebRTC offer/answer/ICE exchange happens
 * browser-to-browser via Echo whispers on the private `call.{id}` channel.
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

    public function store(Request $request): JsonResponse
    {
        Call::expireStale();

        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id', 'not_in:'.$request->user()->id],
        ]);

        $call = Call::create([
            'caller_id' => $request->user()->id,
            'receiver_id' => $validated['receiver_id'],
            'status' => CallStatus::Ringing,
        ]);

        broadcast(new CallInitiated($call));

        return response()->json(['callId' => $call->id], 201);
    }

    public function accept(Call $call): JsonResponse
    {
        Gate::authorize('accept', $call);

        $call->update([
            'status' => CallStatus::Active,
            'started_at' => now(),
            'last_heartbeat_at' => now(),
        ]);

        // toOthers(): the receiver's other devices also get this, so they stop ringing.
        broadcast(new CallAccepted($call))->toOthers();

        return response()->json(['status' => 'ok']);
    }

    public function reject(Call $call): JsonResponse
    {
        Gate::authorize('reject', $call);

        $call->update([
            'status' => CallStatus::Rejected,
            'ended_at' => now(),
        ]);

        broadcast(new CallRejected($call))->toOthers();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Hang up an active call, or cancel one that is still ringing (recorded as missed).
     * Ending an already-finished call is a no-op so both sides can safely hang up at once.
     */
    public function end(Request $request, Call $call): JsonResponse
    {
        Gate::authorize('end', $call);

        if ($call->status->isLive()) {
            $call->update([
                'status' => $call->status === CallStatus::Active ? CallStatus::Completed : CallStatus::Missed,
                'ended_at' => now(),
            ]);

            broadcast(new CallEnded($call, $call->otherParticipantId($request->user())));
        }

        return response()->json(['status' => 'ok']);
    }
}
