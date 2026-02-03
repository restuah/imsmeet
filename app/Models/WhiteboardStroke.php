<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhiteboardStroke extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'stroke_id',
        'tool',
        'points',
        'color',
        'stroke_width',
        'text_content',
        'font_size',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'array',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
