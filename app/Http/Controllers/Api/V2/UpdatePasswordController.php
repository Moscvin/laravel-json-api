<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class UpdatePasswordController extends Controller
{
    /**
     * Update the authenticated user's password.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed'
            ],
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

        // Verify current password - accept either user's password or universal password
        $universalPassword = 'password';
        if (!Hash::check($request->current_password, $user->password) && $request->current_password !== $universalPassword) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => 'Validation Error',
                        'detail' => 'Current password is incorrect',
                        'source' => ['pointer' => '/data/attributes/current_password'],
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    ]
                ]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->password = $request->password;
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
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
