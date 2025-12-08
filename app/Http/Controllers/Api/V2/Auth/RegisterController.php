<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\RegisterRequest;
use LaravelJsonApi\Core\Document\Error;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\Api\V2\Auth\LoginRequest;
use App\Models\User;

class RegisterController extends Controller
{
    /**
     * Handle user registration
     * Creates new user and automatically logs them in
     *
     * @param \App\Http\Requests\Api\V2\Auth\RegisterRequest $request
     *
     * @return \Symfony\Component\HttpFoundation\Response|\LaravelJsonApi\Core\Document\Error
     * @throws \Exception
     */
    public function __invoke(RegisterRequest $request): Response|Error
    {
        User::create([
            'name'              => $request->name,
            'username'          => $request->username ?? $this->generateUsername($request->name),
            'email'             => $request->email,
            'password'          => $request->password,
            'phone'             => $request->phone,
            'type'              => 'user',
            'is_blocked'        => false,
            'last_active_at'    => now(),
        ]);

        // Auto-login after registration
        return (new LoginController)(new LoginRequest($request->only(['email', 'password'])));
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
