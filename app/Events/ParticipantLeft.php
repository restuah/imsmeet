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

class ParticipantLeft implements ShouldBroadcast
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
        return 'participant.left';
    }

    public function broadcastWith(): array
    {
        return [
            'participant_id' => $this->participant->id,
            'user_id' => $this->participant->user_id,
            'display_name' => $this->participant->display_name,
        ];
    }
}
