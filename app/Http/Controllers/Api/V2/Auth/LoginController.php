<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\LoginRequest;
use App\Models\User;
use LaravelJsonApi\Core\Document\Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     * Authenticates user and returns authentication token
     *
     * @param \App\Http\Requests\Api\V2\Auth\LoginRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\LaravelJsonApi\Core\Document\Error
     * @throws \Exception
     */
    public function __invoke(LoginRequest $request): JsonResponse|Error
    {
        // Find user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists
        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Invalid email or password',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user is blocked
        if ($user->isBlocked()) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Your account has been blocked. Please contact support.',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Invalid email or password',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Generate new token and save to remember_token
        $token = Str::random(80);
        $user->remember_token = $token;
        $user->last_active_at = now();
        $user->save();

        // Return token in format compatible with frontend
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => null, // Token doesn't expire
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
            ]
        ], Response::HTTP_OK);
    }
}
