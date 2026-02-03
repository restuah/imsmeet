<?php

namespace App\Events;

use App\Models\Meeting;
use App\Models\MeetingRecording;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RecordingStopped implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public MeetingRecording $recording
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('meeting.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'recording.stopped';
    }

    public function broadcastWith(): array
    {
        return [
            'recording' => [
                'id' => $this->recording->id,
                'ended_at' => $this->recording->ended_at->toISOString(),
            ],
        ];
    }
}
