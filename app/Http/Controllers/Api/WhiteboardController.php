<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\WhiteboardStroke;
use App\Events\WhiteboardStrokeAdded;
use App\Events\WhiteboardStrokeRemoved;
use App\Events\WhiteboardCleared;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WhiteboardController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        if (!$meeting->is_whiteboard_enabled) {
            return response()->json([
                'message' => 'Whiteboard is disabled for this meeting',
            ], 403);
        }

        // Verify user is participant
        $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->firstOrFail();

        $strokes = $meeting->whiteboardStrokes()
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'strokes' => $strokes,
        ]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        if (!$meeting->is_whiteboard_enabled) {
            return response()->json([
                'message' => 'Whiteboard is disabled for this meeting',
            ], 403);
        }

        // Verify user is participant
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        $validated = $request->validate([
            'stroke_id' => ['nullable', 'string', 'max:36'],
            'tool' => ['required', 'string', 'in:pen,eraser,line,rectangle,circle,text'],
            'points' => ['required', 'array'],
            'points.*' => ['array'],
            'color' => ['nullable', 'string', 'max:20'],
            'stroke_width' => ['nullable', 'integer', 'min:1', 'max:50'],
            'text_content' => ['nullable', 'string', 'max:500'],
            'font_size' => ['nullable', 'integer', 'min:8', 'max:72'],
        ]);

        $stroke = WhiteboardStroke::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'stroke_id' => $validated['stroke_id'] ?? Str::uuid(),
            'tool' => $validated['tool'],
            'points' => $validated['points'],
            'color' => $validated['color'] ?? '#000000',
            'stroke_width' => $validated['stroke_width'] ?? 2,
            'text_content' => $validated['text_content'] ?? null,
            'font_size' => $validated['font_size'] ?? null,
        ]);

        $stroke->load('user:id,name');

        broadcast(new WhiteboardStrokeAdded($meeting, $stroke))->toOthers();

        return response()->json([
            'stroke' => $stroke,
        ], 201);
    }

    public function destroy(Request $request, Meeting $meeting, WhiteboardStroke $stroke): JsonResponse
    {
        $user = $request->user();

        // Verify user is participant
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        // Only allow user to delete their own strokes, or host/co-host can delete any
        if ($stroke->user_id !== $user->id && !$participant->canManageParticipants()) {
            return response()->json([
                'message' => 'You can only delete your own strokes',
            ], 403);
        }

        $strokeId = $stroke->stroke_id;
        $stroke->delete();

        broadcast(new WhiteboardStrokeRemoved($meeting, $strokeId))->toOthers();

        return response()->json([
            'message' => 'Stroke deleted',
        ]);
    }

    public function clear(Request $request, Meeting $meeting): JsonResponse
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
                'message' => 'Only hosts and co-hosts can clear the whiteboard',
            ], 403);
        }

        $meeting->whiteboardStrokes()->delete();

        broadcast(new WhiteboardCleared($meeting))->toOthers();

        return response()->json([
            'message' => 'Whiteboard cleared',
        ]);
    }
}
