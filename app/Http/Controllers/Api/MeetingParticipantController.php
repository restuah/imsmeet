<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Events\ParticipantUpdated;
use App\Events\ParticipantKicked;
use App\Events\ParticipantMuted;
use App\Events\ScreenShareStarted;
use App\Events\ScreenShareStopped;
use App\Events\HandRaised;
use App\Events\HandLowered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingParticipantController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        $participants = $meeting->activeParticipants()
            ->with('user:id,name,email,avatar')
            ->get();

        return response()->json([
            'participants' => $participants,
        ]);
    }

    public function mute(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        $participant->mute();

        broadcast(new ParticipantMuted($meeting, $participant, true))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Participant muted',
        ]);
    }

    public function unmute(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        $participant->unmute();

        broadcast(new ParticipantMuted($meeting, $participant, false))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Participant unmuted',
        ]);
    }

    public function kick(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        if ($participant->isHost()) {
            return response()->json([
                'message' => 'Cannot kick the host',
            ], 422);
        }

        $participant->leave();

        broadcast(new ParticipantKicked($meeting, $participant))->toOthers();

        return response()->json([
            'message' => 'Participant removed from meeting',
        ]);
    }

    public function promote(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        if ($participant->isHost()) {
            return response()->json([
                'message' => 'Cannot promote the host',
            ], 422);
        }

        $participant->promoteToCoHost();

        broadcast(new ParticipantUpdated($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Participant promoted to co-host',
        ]);
    }

    public function demote(Request $request, Meeting $meeting, MeetingParticipant $participant): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        if ($participant->isHost()) {
            return response()->json([
                'message' => 'Cannot demote the host',
            ], 422);
        }

        $participant->demoteToParticipant();

        broadcast(new ParticipantUpdated($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Participant demoted',
        ]);
    }

    public function muteAll(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeHostAction($request, $meeting);

        $meeting->activeParticipants()
            ->where('role', '!=', 'host')
            ->update(['is_muted' => true]);

        broadcast(new ParticipantMuted($meeting, null, true, true))->toOthers();

        return response()->json([
            'message' => 'All participants muted',
        ]);
    }

    public function toggleVideo(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        $participant->toggleVideo();

        broadcast(new ParticipantUpdated($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'is_video_off' => $participant->is_video_off,
        ]);
    }

    public function toggleAudio(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        if ($participant->is_muted) {
            $participant->unmute();
        } else {
            $participant->mute();
        }

        broadcast(new ParticipantUpdated($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'is_muted' => $participant->is_muted,
        ]);
    }

    public function startScreenShare(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        // Check if someone else is already sharing
        $existingShare = $meeting->activeParticipants()
            ->where('is_screen_sharing', true)
            ->where('id', '!=', $participant->id)
            ->first();

        if ($existingShare) {
            return response()->json([
                'message' => 'Someone else is already sharing their screen',
            ], 422);
        }

        $participant->startScreenShare();

        broadcast(new ScreenShareStarted($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Screen sharing started',
        ]);
    }

    public function stopScreenShare(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        $participant->stopScreenShare();

        broadcast(new ScreenShareStopped($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Screen sharing stopped',
        ]);
    }

    public function raiseHand(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        $participant->raiseHand();

        broadcast(new HandRaised($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Hand raised',
        ]);
    }

    public function lowerHand(Request $request, Meeting $meeting): JsonResponse
    {
        $participant = $this->getCurrentParticipant($request, $meeting);

        $participant->lowerHand();

        broadcast(new HandLowered($meeting, $participant))->toOthers();

        return response()->json([
            'participant' => $participant,
            'message' => 'Hand lowered',
        ]);
    }

    private function authorizeHostAction(Request $request, Meeting $meeting): void
    {
        $participant = $meeting->participants()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        if (!$participant->canManageParticipants()) {
            abort(403, 'Only hosts and co-hosts can perform this action');
        }
    }

    private function getCurrentParticipant(Request $request, Meeting $meeting): MeetingParticipant
    {
        return $meeting->participants()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();
    }
}
