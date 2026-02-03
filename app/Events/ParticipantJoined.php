<?php

namespace App\Events;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantJoined implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public MeetingParticipant $participant
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('presence-meeting.' . $this->meeting->id),
            new PrivateChannel('meeting.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.joined';
    }

    public function broadcastWith(): array
    {
        return [
            'participant' => [
                'id' => $this->participant->id,
                'user_id' => $this->participant->user_id,
                'display_name' => $this->participant->display_name,
                'role' => $this->participant->role,
                'is_muted' => $this->participant->is_muted,
                'is_video_off' => $this->participant->is_video_off,
                'avatar' => $this->participant->user->avatar ?? null,
            ],
        ];
    }
}
