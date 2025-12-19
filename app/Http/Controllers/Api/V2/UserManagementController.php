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
     * Format user data with readable timestamps
     */
    private function formatUserData($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
            'is_blocked' => $user->is_blocked,
            'last_active_at' => $user->last_active_at ? $user->last_active_at->format('Y-m-d H:i:s') : null,
            'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
        ];
    }

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
            return $this->formatUserData($u);
        });

        $totalUsers = User::count();
        $activeUsers = User::where('is_blocked', false)->count();
        $blockedUsers = User::where('is_blocked', true)->count();

        return response()->json([
            'users' => $users,
            'stats' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'blocked' => $blockedUsers,
            ]
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

        // Extract data - plain JSON format from custom route
        $data = $request->all();

        $validator = Validator::make($data, [
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

        try {
            $newUser = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'type' => $data['type'],
                'is_blocked' => false,
                'remember_token' => Str::random(80),
            ]);

            return response()->json([
                'user' => $this->formatUserData($newUser),
                'message' => 'User created successfully.'
            ], Response::HTTP_CREATED);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle database constraint violations
            $message = 'Failed to create user';
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                if (str_contains($e->getMessage(), 'users_username_unique')) {
                    $message = 'This username is already taken';
                } elseif (str_contains($e->getMessage(), 'users_email_unique')) {
                    $message = 'This email is already registered';
                }
            }
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Database Error',
                        'detail' => $message,
                        'status' => Response::HTTP_CONFLICT,
                    ]
                ]
            ], Response::HTTP_CONFLICT);
        }
    }

    /**
     * Update a user (root only)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can update users.',
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

        return $this->performUserUpdate($request, $targetUser);
    }

    /**
     * Update a user using ID provided in request body (root only)
     */
    public function updateByBody(Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can update users.',
                        'status' => Response::HTTP_FORBIDDEN,
                    ]
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        // Require id in body; no fallback
        $candidateId = $request->input('id');

        // Debug: Log what we received
        \Log::info('UpdateByBody Request', [
            'all_input' => $request->all(),
            'id_received' => $candidateId,
            'id_type' => gettype($candidateId)
        ]);

        if (!$candidateId) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Bad Request',
                        'detail' => 'User id is required. Received: ' . json_encode($request->all()),
                        'status' => Response::HTTP_BAD_REQUEST,
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        $targetUser = User::find($candidateId);

        if (!$targetUser) {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Not Found',
                        'detail' => 'User not found for id: ' . $candidateId . '. All users IDs: ' . User::pluck('id')->implode(', '),
                        'status' => Response::HTTP_NOT_FOUND,
                    ]
                ]
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->performUserUpdate($request, $targetUser);
    }

    /**
     * Shared update logic once target user is resolved
     */
    private function performUserUpdate(Request $request, User $targetUser): JsonResponse
    {
        $data = $request->all();
        $id = $targetUser->id;

        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'type' => 'sometimes|required|in:user,root',
            'is_blocked' => 'sometimes|boolean',
        ];

        $validator = Validator::make($data, $rules);

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

        try {
            // Update only provided fields
            if (isset($data['name'])) {
                $targetUser->name = $data['name'];
            }
            if (isset($data['username'])) {
                $targetUser->username = $data['username'];
            }
            if (isset($data['email'])) {
                $targetUser->email = $data['email'];
            }
            if (!empty($data['password'])) {
                $targetUser->password = $data['password'];
            }
            if (isset($data['phone'])) {
                $targetUser->phone = $data['phone'];
            }
            if (isset($data['type'])) {
                $targetUser->type = $data['type'];
            }
            if (isset($data['is_blocked'])) {
                $targetUser->is_blocked = $data['is_blocked'];
            }

            $targetUser->save();

            return response()->json([
                'user' => $this->formatUserData($targetUser),
                'message' => 'User updated successfully.'
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\QueryException $e) {
            $message = 'Failed to update user';
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                if (str_contains($e->getMessage(), 'users_username_unique')) {
                    $message = 'This username is already taken';
                } elseif (str_contains($e->getMessage(), 'users_email_unique')) {
                    $message = 'This email is already registered';
                }
            }
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Database Error',
                        'detail' => $message,
                        'status' => Response::HTTP_CONFLICT,
                    ]
                ]
            ], Response::HTTP_CONFLICT);
        }
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

    /**
     * Toggle block status for a user (root only)
     */
    public function toggleBlockStatus(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can block/unblock users.',
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

        // Toggle block status
        $targetUser->is_blocked = !$targetUser->is_blocked;
        $targetUser->save();

        return response()->json([
            'user' => $this->formatUserData($targetUser),
            'message' => $targetUser->is_blocked ? 'User blocked successfully.' : 'User unblocked successfully.'
        ], Response::HTTP_OK);
    }

    /**
     * Edit user profile (root only)
     */
    public function editProfile(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if ($user->type !== 'root') {
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Forbidden',
                        'detail' => 'Only root users can edit user profiles.',
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

        $data = $request->all();

        $rules = [
            'username' => 'sometimes|required|string|max:255|unique:users,username,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
        ];

        $validator = Validator::make($data, $rules);

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

        try {
            // Update only provided fields
            if (isset($data['username'])) {
                $targetUser->username = $data['username'];
            }
            if (isset($data['name'])) {
                $targetUser->name = $data['name'];
            }
            if (isset($data['email'])) {
                $targetUser->email = $data['email'];
            }
            if (isset($data['phone'])) {
                $targetUser->phone = $data['phone'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $targetUser->password = $data['password'];
            }

            $targetUser->save();

            return response()->json([
                'user' => $this->formatUserData($targetUser),
                'message' => 'User profile updated successfully.'
            ], Response::HTTP_OK);
        } catch (\Illuminate\Database\QueryException $e) {
            $message = 'Failed to update user profile';
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                if (str_contains($e->getMessage(), 'users_username_unique')) {
                    $message = 'This username is already taken';
                } elseif (str_contains($e->getMessage(), 'users_email_unique')) {
                    $message = 'This email is already registered';
                }
            }
            return response()->json([
                'errors' => [
                    [
                        'title' => 'Database Error',
                        'detail' => $message,
                        'status' => Response::HTTP_CONFLICT,
                    ]
                ]
            ], Response::HTTP_CONFLICT);
        }
    }
}
