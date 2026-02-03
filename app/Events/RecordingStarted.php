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

class RecordingStarted implements ShouldBroadcast
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
        return 'recording.started';
    }

    public function broadcastWith(): array
    {
        return [
            'recording' => [
                'id' => $this->recording->id,
                'started_at' => $this->recording->started_at->toISOString(),
            ],
        ];
    }
}
