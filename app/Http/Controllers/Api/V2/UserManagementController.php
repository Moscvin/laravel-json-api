<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserManagementController extends Controller
{
    /**
     * Get all users (root only)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can access user management.',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $users = User::orderBy('created_at', 'desc')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'phone' => $u->phone,
                'type' => $u->type,
                'is_blocked' => $u->is_blocked,
                'last_active_at' => optional($u->last_active_at)->toIso8601String(),
                'created_at' => optional($u->created_at)->toIso8601String(),
                'updated_at' => optional($u->updated_at)->toIso8601String(),
            ];
        });;

        return response()->json([
            'users' => $users
        ], Response::HTTP_OK);
    }

    /**
     * Create a new user (root only)
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can create users.',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'type' => 'required|in:user,root',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => collect($validator->errors()->messages())->map(function ($errors, $field) {
                    return [
                        'title' => 'Validation Error',
                        'detail' => implode(', ', $errors),
                        'source' => ['pointer' => "/data/attributes/{$field}"],
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    ];
                })->values()->all()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $newUser = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'phone' => $request->phone,
            'type' => $request->type,
            'is_blocked' => false,
            'remember_token' => Str::random(80),
        ]);

        return response()->json([
            'user' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'username' => $newUser->username,
                'email' => $newUser->email,
                'phone' => $newUser->phone,
                'type' => $newUser->type,
                'is_blocked' => $newUser->is_blocked,
                'created_at' => optional($newUser->created_at)->toIso8601String(),
            ],
            'message' => 'User created successfully.'
        ], Response::HTTP_CREATED);
    }

    /**
     * Delete a user (root only, cannot delete root users)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can delete users.',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $targetUser = User::find($id);

        if (!$targetUser) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Not Found',
                        'detail' => 'User not found.',
                        'status' => Response::HTTP_NOT_FOUND,
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        // Prevent deleting root users
        if ($targetUser->type === 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Cannot delete root users.',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        $targetUser->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ], Response::HTTP_OK);
    }
}
