<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AllowedIp;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AllowedIpController extends Controller
{
    /**
     * Get all allowed IPs (root only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Authentication required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user is root
        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Forbidden',
                        'detail' => 'Only root users can access this resource',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $allowedIps = AllowedIp::orderBy('created_at', 'desc')->get();

        return response()->json([
            'data' => $allowedIps,
            'meta' => [
                'count' => $allowedIps->count(),
            ]
        ], Response::HTTP_OK);
    }

    /**
     * Create new allowed IP (root only)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(): JsonResponse
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Authentication required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user is root
        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Forbidden',
                        'detail' => 'Only root users can access this resource',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $ip = request()->input('ip');

        if (!$ip) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Bad Request',
                        'detail' => 'IP address is required',
                        'status' => Response::HTTP_BAD_REQUEST,
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Bad Request',
                        'detail' => 'Invalid IP address format',
                        'status' => Response::HTTP_BAD_REQUEST,
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        $allowedIp = AllowedIp::create(['ip' => $ip]);

        return response()->json([
            'data' => $allowedIp
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete allowed IP (root only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Unauthorized',
                        'detail' => 'Authentication required',
                        'status' => Response::HTTP_UNAUTHORIZED,
                    ]
                ]
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user is root
        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Forbidden',
                        'detail' => 'Only root users can access this resource',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $allowedIp = AllowedIp::find($id);

        if (!$allowedIp) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Not Found',
                        'detail' => 'Allowed IP not found',
                        'status' => Response::HTTP_NOT_FOUND,
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        $allowedIp->delete();

        return response()->json([
            'message' => 'Allowed IP deleted successfully'
        ], Response::HTTP_OK);
    }
}
