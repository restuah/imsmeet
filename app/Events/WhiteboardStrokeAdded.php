<?php

namespace App\Events;

use App\Models\Meeting;
use App\Models\WhiteboardStroke;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhiteboardStrokeAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public WhiteboardStroke $stroke
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whiteboard.' . $this->meeting->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stroke.added';
    }

    public function broadcastWith(): array
    {
        return [
            'stroke' => [
                'id' => $this->stroke->id,
                'stroke_id' => $this->stroke->stroke_id,
                'user_id' => $this->stroke->user_id,
                'tool' => $this->stroke->tool,
                'points' => $this->stroke->points,
                'color' => $this->stroke->color,
                'stroke_width' => $this->stroke->stroke_width,
                'text_content' => $this->stroke->text_content,
                'font_size' => $this->stroke->font_size,
                'user' => [
                    'id' => $this->stroke->user->id,
                    'name' => $this->stroke->user->name,
                ],
            ],
        ];
    }
}
