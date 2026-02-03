<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'host_id',
        'title',
        'description',
        'password',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'max_participants',
        'is_recording_enabled',
        'is_chat_enabled',
        'is_whiteboard_enabled',
        'waiting_room_enabled',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_recording_enabled' => 'boolean',
            'is_chat_enabled' => 'boolean',
            'is_whiteboard_enabled' => 'boolean',
            'waiting_room_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($meeting) {
            if (empty($meeting->uuid)) {
                $meeting->uuid = Str::uuid();
            }
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class)
            ->whereNotNull('joined_at')
            ->whereNull('left_at');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function whiteboardStrokes(): HasMany
    {
        return $this->hasMany(WhiteboardStroke::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    public function end(): void
    {
        $this->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
    }

    public function getJoinUrlAttribute(): string
    {
        return url("/meeting/{$this->uuid}");
    }

    public function hasPassword(): bool
    {
        return !empty($this->password);
    }

    public function verifyPassword(string $password): bool
    {
        return $this->password === $password;
    }

    public function canUserJoin(User $user): bool
    {
        if ($this->isEnded()) {
            return false;
        }

        if ($this->activeParticipants()->count() >= $this->max_participants) {
            return false;
        }

        return true;
    }

    public function isHost(User $user): bool
    {
        return $this->host_id === $user->id;
    }
}
