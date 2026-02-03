<?php

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhiteboardStrokeRemoved implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public string $strokeId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whiteboard.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stroke.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'stroke_id' => $this->strokeId,
        ];
    }
}
