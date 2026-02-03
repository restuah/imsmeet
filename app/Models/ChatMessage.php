<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'message',
        'type',
        'file_path',
        'file_name',
        'is_private',
        'recipient_id',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
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

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isSystemMessage(): bool
    {
        return $this->type === 'system';
    }

    public function isFileMessage(): bool
    {
        return $this->type === 'file';
    }

    public function isPrivateMessage(): bool
    {
        return $this->is_private;
    }
}
