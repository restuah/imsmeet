<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        // Admin can view any meeting
        if ($user->isAdmin()) {
            return true;
        }

        // User can view if they are host or participant
        return $meeting->host_id === $user->id ||
            $meeting->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $meeting->host_id === $user->id;
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $meeting->host_id === $user->id;
    }

    public function manage(User $user, Meeting $meeting): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $participant = $meeting->participants()
            ->where('user_id', $user->id)
            ->whereIn('role', ['host', 'co-host'])
            ->first();

        return $participant !== null;
    }
}
