<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MeetingRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'recorded_by',
        'file_path',
        'file_name',
        'file_size',
        'duration',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isRecording(): bool
    {
        return $this->status === 'recording';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->isCompleted() || empty($this->file_path)) {
            return null;
        }

        return Storage::url($this->file_path);
    }

    public function getFormattedDuration(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
