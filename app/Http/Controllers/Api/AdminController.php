<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $query = User::with('roles')
            ->withCount(['hostedMeetings', 'participatedMeetings']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->get('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        if (in_array($validated['role'], ['admin', 'superadmin']) && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only superadmin can create admin users',
            ], 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'User created successfully',
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($user->isSuperAdmin() && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Cannot edit superadmin user',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', Password::defaults()],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'User updated successfully',
        ]);
    }

    public function deleteUser(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Cannot delete your own account',
            ], 422);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Cannot delete superadmin user',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        if (in_array($validated['role'], ['admin', 'superadmin']) && !$request->user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only superadmin can assign admin roles',
            ], 403);
        }

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'user' => $user->load('roles'),
            'message' => 'Role assigned successfully',
        ]);
    }

    public function meetings(Request $request): JsonResponse
    {
        $query = Meeting::with(['host:id,name,email'])
            ->withCount('participants');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('host', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $meetings = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($meetings);
    }

    public function statistics(): JsonResponse
    {
        $stats = [
            'total_users' => User::count(),
            'total_meetings' => Meeting::count(),
            'active_meetings' => Meeting::where('status', 'active')->count(),
            'total_meetings_today' => Meeting::whereDate('created_at', today())->count(),
            'users_by_role' => [
                'superadmin' => User::role('superadmin')->count(),
                'admin' => User::role('admin')->count(),
                'user' => User::role('user')->count(),
            ],
            'meetings_by_status' => [
                'scheduled' => Meeting::where('status', 'scheduled')->count(),
                'active' => Meeting::where('status', 'active')->count(),
                'ended' => Meeting::where('status', 'ended')->count(),
            ],
        ];

        return response()->json($stats);
    }

    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'role' => $role->load('permissions'),
            'message' => 'Role created successfully',
        ], 201);
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::all();

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions']);

        return response()->json([
            'role' => $role->load('permissions'),
            'message' => 'Permissions updated successfully',
        ]);
    }

    public function systemSettings(): JsonResponse
    {
        return response()->json([
            'settings' => [
                'max_meeting_participants' => config('meeting.max_participants', 100),
                'max_recording_duration' => config('meeting.max_recording_duration', 3600),
                'default_meeting_duration' => config('meeting.default_duration', 60),
                'enable_recordings' => config('meeting.enable_recordings', true),
                'enable_chat' => config('meeting.enable_chat', true),
                'enable_whiteboard' => config('meeting.enable_whiteboard', true),
            ],
        ]);
    }

    public function updateSystemSettings(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Settings updated successfully',
        ]);
    }
}
