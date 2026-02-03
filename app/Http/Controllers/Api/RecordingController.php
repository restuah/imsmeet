<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingRecording;
use App\Events\RecordingStarted;
use App\Events\RecordingStopped;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecordingController extends Controller
{
    public function index(Meeting $meeting): JsonResponse
    {
        $recordings = $meeting->recordings()
            ->with('recordedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'recordings' => $recordings,
        ]);
    }

    public function start(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        if (!$meeting->is_recording_enabled) {
            return response()->json([
                'message' => 'Recording is disabled for this meeting',
            ], 403);
        }

        // Verify user is host or co-host
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        if (!$participant->canManageParticipants()) {
            return response()->json([
                'message' => 'Only hosts and co-hosts can start recording',
            ], 403);
        }

        // Check if there's already an active recording
        $existingRecording = $meeting->recordings()
            ->where('status', 'recording')
            ->first();

        if ($existingRecording) {
            return response()->json([
                'message' => 'Recording is already in progress',
            ], 422);
        }

        $recording = MeetingRecording::create([
            'meeting_id' => $meeting->id,
            'recorded_by' => $user->id,
            'file_path' => '',
            'file_name' => 'meeting_' . $meeting->uuid . '_' . now()->format('Y-m-d_H-i-s') . '.webm',
            'status' => 'recording',
            'started_at' => now(),
        ]);

        broadcast(new RecordingStarted($meeting, $recording))->toOthers();

        return response()->json([
            'recording' => $recording,
            'message' => 'Recording started',
        ], 201);
    }

    public function stop(Request $request, Meeting $meeting, MeetingRecording $recording): JsonResponse
    {
        $user = $request->user();

        // Verify user is host or co-host
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        if (!$participant->canManageParticipants()) {
            return response()->json([
                'message' => 'Only hosts and co-hosts can stop recording',
            ], 403);
        }

        if (!$recording->isRecording()) {
            return response()->json([
                'message' => 'Recording is not active',
            ], 422);
        }

        $recording->update([
            'status' => 'processing',
            'ended_at' => now(),
        ]);

        broadcast(new RecordingStopped($meeting, $recording))->toOthers();

        return response()->json([
            'recording' => $recording,
            'message' => 'Recording stopped, waiting for upload',
        ]);
    }

    public function upload(Request $request, MeetingRecording $recording): JsonResponse
    {
        $user = $request->user();

        // Verify user is the one who started recording
        if ($recording->recorded_by !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'recording' => ['required', 'file', 'mimetypes:video/webm,video/mp4', 'max:524288'], // 512MB max
            'duration' => ['required', 'integer', 'min:1'],
        ]);

        $file = $request->file('recording');
        $path = $file->store('recordings/' . $recording->meeting_id, 'public');

        $recording->update([
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'duration' => $validated['duration'],
            'status' => 'completed',
        ]);

        return response()->json([
            'recording' => $recording,
            'message' => 'Recording uploaded successfully',
        ]);
    }

    public function download(MeetingRecording $recording): mixed
    {
        if (!$recording->isCompleted() || empty($recording->file_path)) {
            return response()->json([
                'message' => 'Recording not available',
            ], 404);
        }

        if (!Storage::disk('public')->exists($recording->file_path)) {
            return response()->json([
                'message' => 'Recording file not found',
            ], 404);
        }

        return Storage::disk('public')->download(
            $recording->file_path,
            $recording->file_name
        );
    }

    public function destroy(Request $request, MeetingRecording $recording): JsonResponse
    {
        $user = $request->user();

        // Only recording creator or admin can delete
        if ($recording->recorded_by !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        // Delete file if exists
        if (!empty($recording->file_path) && Storage::disk('public')->exists($recording->file_path)) {
            Storage::disk('public')->delete($recording->file_path);
        }

        $recording->delete();

        return response()->json([
            'message' => 'Recording deleted',
        ]);
    }
}
