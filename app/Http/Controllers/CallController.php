<?php

namespace App\Http\Controllers;

use App\Events\CallAccepted;
use App\Events\CallEnded;
use App\Events\CallInitiated;
use App\Events\CallRejected;
use App\Events\WebRTCAnswer;
use App\Events\WebRTCIceCandidate;
use App\Events\WebRTCOffer;
use App\Models\Call;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function initiateCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();

        Call::create([
            'caller_id' => $sender->id,
            'receiver_id' => $validated['to_user_id'],
            'status' => 'pending',
        ]);

        broadcast(new CallInitiated(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
            fromUserName: $sender->name,
        ));

        return response()->json(['status' => 'ok']);
    }

    public function acceptCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();

        broadcast(new CallAccepted(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
        ));

        return response()->json(['status' => 'ok']);
    }

    public function rejectCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();

        Call::where('caller_id', $validated['to_user_id'])
            ->where('receiver_id', $sender->id)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);

        broadcast(new CallRejected(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
        ));

        return response()->json(['status' => 'ok']);
    }

    public function endCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $sender = $request->user();

        Call::where(function ($query) use ($sender, $validated): void {
            $query->where('caller_id', $sender->id)
                ->where('receiver_id', $validated['to_user_id']);
        })->orWhere(function ($query) use ($sender, $validated): void {
            $query->where('caller_id', $validated['to_user_id'])
                ->where('receiver_id', $sender->id);
        })->whereIn('status', ['pending', 'active'])
            ->update(['status' => 'completed', 'ended_at' => now()]);

        broadcast(new CallEnded(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
        ));

        return response()->json(['status' => 'ok']);
    }

    public function sendOffer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'offer' => ['required', 'array'],
        ]);

        $sender = $request->user();

        broadcast(new WebRTCOffer(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
            offer: (object) $validated['offer'],
        ));

        return response()->json(['status' => 'ok']);
    }

    public function sendAnswer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'answer' => ['required', 'array'],
        ]);

        $sender = $request->user();

        broadcast(new WebRTCAnswer(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
            answer: (object) $validated['answer'],
        ));

        return response()->json(['status' => 'ok']);
    }

    public function sendCandidate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'candidate' => ['required', 'array'],
        ]);

        $sender = $request->user();

        broadcast(new WebRTCIceCandidate(
            toUserId: $validated['to_user_id'],
            fromUserId: $sender->id,
            candidate: (object) $validated['candidate'],
        ));

        return response()->json(['status' => 'ok']);
    }
}
