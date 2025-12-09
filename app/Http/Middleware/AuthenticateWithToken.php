<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     * Authenticates user based on Bearer token from remember_token column
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            $authHeader = $request->header('Authorization');
            if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            }
        }

        if (!$token) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Authentication token is required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = User::where('remember_token', $token)->first();

        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Invalid authentication token',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

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

        // Set authenticated user
        auth()->setUser($user);

        // Update last active timestamp
        $user->updateLastActive();

        return $next($request);
    }
}
