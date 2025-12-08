<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\RegisterRequest;
use LaravelJsonApi\Core\Document\Error;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Handle user registration
     * Creates new user and automatically logs them in
     *
     * @param \App\Http\Requests\Api\V2\Auth\RegisterRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\LaravelJsonApi\Core\Document\Error
     * @throws \Exception
     */
    public function __invoke(RegisterRequest $request): JsonResponse|Error
    {
        // Generate authentication token
        $token = Str::random(80);

        // Create user with token
        $user = User::create([
            'name'              => $request->name,
            'username'          => $request->username ?? $this->generateUsername($request->name),
            'email'             => $request->email,
            'password'          => $request->password,
            'phone'             => $request->phone,
            'type'              => 'user',
            'is_blocked'        => false,
            'last_active_at'    => now(),
            'remember_token'    => $token,
        ]);

        // Return token in format compatible with frontend
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => null, // Token doesn't expire
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'username' => $user->username,
                'type'     => $user->type,
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Generate OAuth token for user
     *
     * @param string $email
     * @param string $password
     * @return \Illuminate\Http\JsonResponse
     */
    private function generateAuthToken(string $email, string $password): JsonResponse
    {
        $client = DB::table('oauth_clients')->where('password_client', 1)->first();

        $oauthRequest = Request::create(config('app.url') . '/oauth/token', 'POST', [
            'grant_type'    => 'password',
            'client_id'     => $client->id,
            'client_secret' => $client->secret,
            'username'      => $email,
            'password'      => $password,
            'scope'         => '',
        ]);

        /** @var \Illuminate\Http\Response $response */
        $response = app()->handle($oauthRequest);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return response()->json([
                'errors' => [
                    [
                        'title'  => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                        'detail' => 'Failed to generate authentication token',
                        'status' => Response::HTTP_BAD_REQUEST,
                    ]
                ]
            ], Response::HTTP_BAD_REQUEST);
        }

        // Decode and return the token response
        $tokenData = json_decode($response->getContent(), true);

        return response()->json($tokenData, Response::HTTP_OK);
    }

    /**
     * Generate unique username from name
     *
     * @param string $name
     * @return string
     */
    private function generateUsername($name): string
    {
        $username = strtolower(str_replace(' ', '.', $name));
        $count = User::where('username', 'like', $username . '%')->count();
        return $count > 0 ? $username . '.' . ($count + 1) : $username;
    }
}
