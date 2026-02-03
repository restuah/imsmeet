<?php

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Meeting presence channel - for tracking participants
Broadcast::channel('presence-meeting.{meetingId}', function (User $user, int $meetingId) {
    $meeting = Meeting::find($meetingId);

    if (!$meeting) {
        return false;
    }

    $participant = $meeting->participants()
        ->where('user_id', $user->id)
        ->whereNotNull('joined_at')
        ->whereNull('left_at')
        ->first();

    if (!$participant) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $participant->display_name,
        'role' => $participant->role,
        'avatar' => $user->avatar,
        'is_muted' => $participant->is_muted,
        'is_video_off' => $participant->is_video_off,
        'is_screen_sharing' => $participant->is_screen_sharing,
        'is_hand_raised' => $participant->is_hand_raised,
    ];
});

// Private meeting channel - for signaling and events
Broadcast::channel('meeting.{meetingId}', function (User $user, int $meetingId) {
    $meeting = Meeting::find($meetingId);

    if (!$meeting) {
        return false;
    }

    $participant = $meeting->participants()
        ->where('user_id', $user->id)
        ->whereNotNull('joined_at')
        ->whereNull('left_at')
        ->first();

    return $participant !== null;
});

// Chat channel
Broadcast::channel('chat.{meetingId}', function (User $user, int $meetingId) {
    $meeting = Meeting::find($meetingId);

    if (!$meeting || !$meeting->is_chat_enabled) {
        return false;
    }

    $participant = $meeting->participants()
        ->where('user_id', $user->id)
        ->whereNotNull('joined_at')
        ->whereNull('left_at')
        ->first();

    return $participant !== null;
});

// Whiteboard channel
Broadcast::channel('whiteboard.{meetingId}', function (User $user, int $meetingId) {
    $meeting = Meeting::find($meetingId);

    if (!$meeting || !$meeting->is_whiteboard_enabled) {
        return false;
    }

    $participant = $meeting->participants()
        ->where('user_id', $user->id)
        ->whereNotNull('joined_at')
        ->whereNull('left_at')
        ->first();

    return $participant !== null;
});

// Recording channel - only for hosts/co-hosts
Broadcast::channel('recording.{meetingId}', function (User $user, int $meetingId) {
    $meeting = Meeting::find($meetingId);

    if (!$meeting) {
        return false;
    }

    $participant = $meeting->participants()
        ->where('user_id', $user->id)
        ->whereIn('role', ['host', 'co-host'])
        ->whereNotNull('joined_at')
        ->whereNull('left_at')
        ->first();

    return $participant !== null;
});

// User notifications channel
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
