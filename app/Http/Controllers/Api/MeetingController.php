<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Events\MeetingStarted;
use App\Events\MeetingEnded;
use App\Events\ParticipantJoined;
use App\Events\ParticipantLeft;
use App\Events\ParticipantUpdated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MeetingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Meeting::with(['host:id,name,email,avatar'])
            ->withCount('activeParticipants');

        // Non-admin users only see their own meetings
        // if (!$user->isAdmin()) {
        //     $query->where(function ($q) use ($user) {
        //         $q->where('host_id', $user->id)
        //             ->orWhereHas('participants', function ($q) use ($user) {
        //                 $q->where('user_id', $user->id);
        //             });
        //     });
        // }
        if (!$user->isAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'active')
                    ->orWhere('host_id', $user->id)
                    ->orWhereHas('participants', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
            });
        }

        $meetings = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($meetings);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', 'max:50'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:100'],
            'is_recording_enabled' => ['nullable', 'boolean'],
            'is_chat_enabled' => ['nullable', 'boolean'],
            'is_whiteboard_enabled' => ['nullable', 'boolean'],
            'waiting_room_enabled' => ['nullable', 'boolean'],
        ]);

        $meeting = Meeting::create([
            'uuid' => Str::uuid(),
            'host_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'password' => $validated['password'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'max_participants' => $validated['max_participants'] ?? 50,
            'is_recording_enabled' => $validated['is_recording_enabled'] ?? false,
            'is_chat_enabled' => $validated['is_chat_enabled'] ?? true,
            'is_whiteboard_enabled' => $validated['is_whiteboard_enabled'] ?? true,
            'waiting_room_enabled' => $validated['waiting_room_enabled'] ?? false,
            'status' => 'scheduled',
        ]);

        // Add host as participant
        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'user_id' => $request->user()->id,
            'display_name' => $request->user()->name,
            'role' => 'host',
        ]);

        return response()->json([
            'meeting' => $meeting->load('host:id,name,email,avatar'),
            'message' => 'Meeting created successfully',
        ], 201);
    }

    public function show(Meeting $meeting): JsonResponse
    {
        return response()->json([
            'meeting' => $meeting->load([
                'host:id,name,email,avatar',
                'activeParticipants.user:id,name,email,avatar',
            ]),
        ]);
    }

    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'password' => ['nullable', 'string', 'max:50'],
            'scheduled_at' => ['nullable', 'date'],
            'max_participants' => ['nullable', 'integer', 'min:2', 'max:100'],
            'is_recording_enabled' => ['nullable', 'boolean'],
            'is_chat_enabled' => ['nullable', 'boolean'],
            'is_whiteboard_enabled' => ['nullable', 'boolean'],
            'waiting_room_enabled' => ['nullable', 'boolean'],
        ]);

        $meeting->update($validated);

        return response()->json([
            'meeting' => $meeting->fresh()->load('host:id,name,email,avatar'),
            'message' => 'Meeting updated successfully',
        ]);
    }

    public function destroy(Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        if ($meeting->isActive()) {
            return response()->json([
                'message' => 'Cannot delete an active meeting. End the meeting first.',
            ], 422);
        }

        $meeting->delete();

        return response()->json([
            'message' => 'Meeting deleted successfully',
        ]);
    }

    public function start(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manage', $meeting);

        if ($meeting->isActive()) {
            return response()->json([
                'message' => 'Meeting is already active',
            ], 422);
        }

        if ($meeting->isEnded()) {
            return response()->json([
                'message' => 'Cannot start an ended meeting',
            ], 422);
        }

        $meeting->start();

        broadcast(new MeetingStarted($meeting))->toOthers();

        return response()->json([
            'meeting' => $meeting->fresh()->load('host:id,name,email,avatar'),
            'message' => 'Meeting started successfully',
        ]);
    }

    public function end(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('manage', $meeting);

        if (!$meeting->isActive()) {
            return response()->json([
                'message' => 'Meeting is not active',
            ], 422);
        }

        // Mark all active participants as left
        $meeting->activeParticipants()->update([
            'left_at' => now(),
        ]);

        $meeting->end();

        // broadcast(new MeetingEnded($meeting))->toOthers();
        broadcast(new MeetingEnded($meeting));

        return response()->json([
            'meeting' => $meeting->fresh(),
            'message' => 'Meeting ended successfully',
        ]);
    }

    /*
    public function join(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['nullable', 'string'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Check password if required
        if ($meeting->hasPassword()) {
            if (!isset($validated['password']) || !$meeting->verifyPassword($validated['password'])) {
                return response()->json([
                    'message' => 'Invalid meeting password',
                ], 403);
            }
        }

        // Check if user can join
        if (!$meeting->canUserJoin($user)) {
            return response()->json([
                'message' => 'Cannot join this meeting',
            ], 403);
        }

        if ($meeting->isEnded()) {
            return response()->json([
                'message' => 'This meeting has ended and cannot be rejoined',
            ], 403);
        }

        // Get or create participant record
        $participant = MeetingParticipant::firstOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ],
            [
                'display_name' => $validated['display_name'] ?? $user->name,
                'role' => $meeting->isHost($user) ? 'host' : 'participant',
            ]
        );

        $participant->update([
            'is_muted' => false,        // Reset ke unmuted
            'is_video_off' => false,    // Reset ke video on
            'is_hand_raised' => false,  // Reset hand
            'is_screen_sharing' => false,
            'left_at' => null,          // Clear left_at
        ]);

        // If waiting room is enabled and user is not host/co-host
        if ($meeting->waiting_room_enabled && !$participant->canManageParticipants()) {
            $participant->update([
                'is_in_waiting_room' => true,
            ]);

            return response()->json([
                'meeting' => $meeting->load('host:id,name,email,avatar'),
                'participant' => $participant,
                'in_waiting_room' => true,
                'message' => 'You are in the waiting room',
            ]);
        }

        $participant->join();

        broadcast(new ParticipantJoined($meeting, $participant))->toOthers();

        return response()->json([
            'meeting' => $meeting->load([
                'host:id,name,email,avatar',
                'activeParticipants.user:id,name,email,avatar',
            ]),
            'participant' => $participant,
            'in_waiting_room' => false,
            'message' => 'Joined meeting successfully',
        ]);
    }
    */
    public function join(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['nullable', 'string'],
            'display_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if meeting is ended
        if ($meeting->isEnded()) {
            return response()->json([
                'message' => 'This meeting has ended',
            ], 403);
        }

        // Check password if required
        if ($meeting->hasPassword()) {
            if (!isset($validated['password']) || !$meeting->verifyPassword($validated['password'])) {
                return response()->json([
                    'message' => 'Invalid meeting password',
                ], 403);
            }
        }

        // Check if user can join
        if (!$meeting->canUserJoin($user)) {
            return response()->json([
                'message' => 'Cannot join this meeting',
            ], 403);
        }

        // Get or create participant record
        $participant = MeetingParticipant::firstOrCreate(
            [
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
            ],
            [
                'display_name' => $validated['display_name'] ?? $user->name,
                'role' => $meeting->isHost($user) ? 'host' : 'participant',
            ]
        );

        // CRITICAL: Reset media state on rejoin
        $participant->update([
            'is_muted' => false,
            'is_video_off' => false,
            'is_hand_raised' => false,
            'is_screen_sharing' => false,
            'left_at' => null,
        ]);

        // If waiting room is enabled and user is not host/co-host
        if ($meeting->waiting_room_enabled && !$participant->canManageParticipants()) {
            $participant->update([
                'is_in_waiting_room' => true,
            ]);

            return response()->json([
                'meeting' => $meeting->load('host:id,name,email,avatar'),
                'participant' => $participant,
                'in_waiting_room' => true,
                'message' => 'You are in the waiting room',
            ]);
        }

        $participant->join();

        broadcast(new ParticipantJoined($meeting, $participant))->toOthers();

        return response()->json([
            'meeting' => $meeting->load([
                'host:id,name,email,avatar',
                'activeParticipants.user:id,name,email,avatar',
            ]),
            'participant' => $participant,
            'in_waiting_room' => false,
            'message' => 'Joined meeting successfully',
        ]);
    }

    /*
    public function leave(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'You are not in this meeting',
            ], 422);
        }

        $participant->leave();

        broadcast(new ParticipantLeft($meeting, $participant))->toOthers();

        // If host leaves and meeting is active, end meeting or transfer host
        if ($participant->isHost() && $meeting->isActive()) {
            $newHost = $meeting->activeParticipants()
                ->where('role', 'co-host')
                ->orWhere('role', 'participant')
                ->first();

            if ($newHost) {
                $newHost->update(['role' => 'host']);
            } else {
                $meeting->end();
                broadcast(new MeetingEnded($meeting))->toOthers();
            }
        }

        return response()->json([
            'message' => 'Left meeting successfully',
        ]);
    }
    */
    public function leave(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'You are not in this meeting',
            ], 422);
        }

        // Reset all participant state before leaving
        $participant->update([
            'is_muted' => false,
            'is_video_off' => false,
            'is_hand_raised' => false,
            'is_screen_sharing' => false,
        ]);

        $participant->leave();

        // Broadcast to all others
        broadcast(new ParticipantLeft($meeting, $participant))->toOthers();

        // Handle host leaving
        if ($participant->isHost() && $meeting->isActive()) {
            $newHost = $meeting->activeParticipants()
                ->whereIn('role', ['co-host', 'participant'])
                ->orderByRaw("FIELD(role, 'co-host', 'participant')")
                ->first();

            if ($newHost) {
                $newHost->update(['role' => 'host']);
                broadcast(new ParticipantUpdated($meeting, $newHost))->toOthers();
            } else {
                $meeting->end();
                broadcast(new MeetingEnded($meeting));
            }
        }

        return response()->json([
            'message' => 'Left meeting successfully',
        ]);
    }

    public function joinByUuid(Request $request, string $uuid): JsonResponse
    {
        $meeting = Meeting::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'meeting' => [
                'id' => $meeting->id,
                'uuid' => $meeting->uuid,
                'title' => $meeting->title,
                'status' => $meeting->status,
                'has_password' => $meeting->hasPassword(),
                'host' => $meeting->host->only(['id', 'name', 'avatar']),
                'is_chat_enabled' => $meeting->is_chat_enabled,
                'is_whiteboard_enabled' => $meeting->is_whiteboard_enabled,
            ],
        ]);
    }

    public function getIceServers(Request $request, Meeting $meeting): JsonResponse
    {
        // Verify user is participant
        $participant = $meeting->participants()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->first();

        if (!$participant) {
            return response()->json([
                'message' => 'You are not in this meeting',
            ], 403);
        }

        // Generate time-limited TURN credentials
        $turnSecret = config('webrtc.turn_secret', env('TURN_SECRET'));
        $turnServer = config('webrtc.turn_server', env('TURN_SERVER', 'imsmeet.ichijoumanagementsystem.co.id'));
        $turnUsername = config('webrtc.turn_username', env('TURN_USERNAME', 'imsmeetuser'));
        $turnPassword = config('webrtc.turn_password', env('TURN_PASSWORD', 'imsmeetp@ssword'));

        // For production, use time-limited credentials:
        // $timestamp = time() + 24 * 3600; // 24 hours validity
        // $username = $timestamp . ':' . $request->user()->id;
        // $credential = base64_encode(hash_hmac('sha1', $username, $turnSecret, true));

        $iceServers = [
            // STUN servers (public)
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun.l.google.com:5349'],
            ['urls' => 'stun:stun1.l.google.com:3478'],
            ['urls' => 'stun:stun1.l.google.com:5349'],
            ['urls' => 'stun:stun2.l.google.com:19302'],
            ['urls' => 'stun:stun2.l.google.com:5349'],
            ['urls' => 'stun:stun3.l.google.com:3478'],
            ['urls' => 'stun:stun3.l.google.com:5349'],
            ['urls' => 'stun:stun4.l.google.com:19302'],
            ['urls' => 'stun:stun4.l.google.com:5349']
        ];

        // Add TURN server if configured
        if ($turnServer) {
            $iceServers[] = [
                'urls' => "turn:{$turnServer}:3478",
                'username' => $turnUsername,
                'credential' => $turnPassword,
            ];

            $iceServers[] = [
                'urls' => "turns:{$turnServer}:5349",
                'username' => $turnUsername,
                'credential' => $turnPassword,
            ];
        }

        return response()->json([
            'iceServers' => $iceServers,
        ]);
    }
}
