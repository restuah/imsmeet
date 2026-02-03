<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $users = User::where('id', '!=', $request->user()->id)
            ->where(function ($q) use ($validated) {
                $q->where('name', 'like', '%' . $validated['query'] . '%')
                    ->orWhere('email', 'like', '%' . $validated['query'] . '%');
            })
            ->select('id', 'name', 'email', 'avatar')
            ->limit(10)
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }
}
