<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    /**
     * Return the authenticated user's profile data.
     */
    public function __invoke(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'username'       => $user->username,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'type'           => $user->type,
                'is_blocked'     => $user->is_blocked,
                'last_active_at' => optional($user->last_active_at)->toIso8601String(),
                'created_at'     => optional($user->created_at)->toIso8601String(),
                'updated_at'     => optional($user->updated_at)->toIso8601String(),
            ],
        ]);
    }
}
