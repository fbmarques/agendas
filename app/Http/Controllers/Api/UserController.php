<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserController extends Controller
{
    public function index(Request $request): JsonResource|JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return JsonResource::collection(
            User::query()
                ->orderBy('full_name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'email' => $u->email,
                    'full_name' => $u->full_name,
                    'role' => $u->role,
                    'created_at' => $u->created_at,
                ])
        );
    }
}
