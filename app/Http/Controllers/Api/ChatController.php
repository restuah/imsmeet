<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\ChatMessage;
use App\Events\ChatMessageSent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        // Verify user is participant
        $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->firstOrFail();

        $messages = $meeting->chatMessages()
            ->with('user:id,name,avatar')
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 50));

        return response()->json($messages);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        if (!$meeting->is_chat_enabled) {
            return response()->json([
                'message' => 'Chat is disabled for this meeting',
            ], 403);
        }

        // Verify user is participant
        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereNotNull('joined_at')
            ->whereNull('left_at')
            ->firstOrFail();

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'type' => ['nullable', 'string', 'in:text,file'],
            'is_private' => ['nullable', 'boolean'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // If private message, recipient is required
        if (($validated['is_private'] ?? false) && empty($validated['recipient_id'])) {
            return response()->json([
                'message' => 'Recipient is required for private messages',
            ], 422);
        }

        $chatMessage = ChatMessage::create([
            'meeting_id' => $meeting->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'type' => $validated['type'] ?? 'text',
            'is_private' => $validated['is_private'] ?? false,
            'recipient_id' => $validated['recipient_id'] ?? null,
        ]);

        $chatMessage->load('user:id,name,avatar');

        broadcast(new ChatMessageSent($meeting, $chatMessage))->toOthers();

        return response()->json([
            'message' => $chatMessage,
        ], 201);
    }
}
