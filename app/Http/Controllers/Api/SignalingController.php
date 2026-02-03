<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Events\WebRTCSignal;
use App\Events\IceCandidate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignalingController extends Controller
{
    public function signal(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:offer,answer'],
            'sdp' => ['required', 'string'],
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        // Verify user is participant
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        broadcast(new WebRTCSignal(
            meeting: $meeting,
            fromUserId: $user->id,
            toUserId: $validated['target_user_id'],
            type: $validated['type'],
            sdp: $validated['sdp'],
            fromUserName: $participant->display_name
        ))->toOthers();

        return response()->json([
            'message' => 'Signal sent successfully',
        ]);
    }

    public function iceCandidate(Request $request, Meeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'candidate' => ['required', 'array'],
            'candidate.candidate' => ['required', 'string'],
            'candidate.sdpMid' => ['nullable', 'string'],
            'candidate.sdpMLineIndex' => ['nullable', 'integer'],
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();

        // Verify user is participant
        $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        broadcast(new IceCandidate(
            meeting: $meeting,
            fromUserId: $user->id,
            toUserId: $validated['target_user_id'],
            candidate: $validated['candidate']
        ))->toOthers();

        return response()->json([
            'message' => 'ICE candidate sent successfully',
        ]);
    }
}
