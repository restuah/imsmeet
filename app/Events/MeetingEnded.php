<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('meeting.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'meeting.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'ended_at' => $this->meeting->ended_at->toISOString(),
        ];
    }
}
