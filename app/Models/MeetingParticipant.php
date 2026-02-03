<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'user_id',
        'display_name',
        'role',
        'is_muted',
        'is_video_off',
        'is_screen_sharing',
        'is_hand_raised',
        'is_in_waiting_room',
        'joined_at',
        'left_at',
        'connection_id',
    ];

    protected function casts(): array
    {
        return [
            'is_muted' => 'boolean',
            'is_video_off' => 'boolean',
            'is_screen_sharing' => 'boolean',
            'is_hand_raised' => 'boolean',
            'is_in_waiting_room' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
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

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function isCoHost(): bool
    {
        return $this->role === 'co-host';
    }

    public function canManageParticipants(): bool
    {
        return in_array($this->role, ['host', 'co-host']);
    }

    public function join(): void
    {
        $this->update([
            'joined_at' => now(),
            'left_at' => null,
            'is_in_waiting_room' => false,
        ]);
    }

    public function leave(): void
    {
        $this->update([
            'left_at' => now(),
            'is_screen_sharing' => false,
        ]);
    }

    public function mute(): void
    {
        $this->update(['is_muted' => true]);
    }

    public function unmute(): void
    {
        $this->update(['is_muted' => false]);
    }

    public function toggleVideo(): void
    {
        $this->update(['is_video_off' => !$this->is_video_off]);
    }

    public function startScreenShare(): void
    {
        $this->update(['is_screen_sharing' => true]);
    }

    public function stopScreenShare(): void
    {
        $this->update(['is_screen_sharing' => false]);
    }

    public function raiseHand(): void
    {
        $this->update(['is_hand_raised' => true]);
    }

    public function lowerHand(): void
    {
        $this->update(['is_hand_raised' => false]);
    }

    public function promoteToCoHost(): void
    {
        $this->update(['role' => 'co-host']);
    }

    public function demoteToParticipant(): void
    {
        $this->update(['role' => 'participant']);
    }
}
