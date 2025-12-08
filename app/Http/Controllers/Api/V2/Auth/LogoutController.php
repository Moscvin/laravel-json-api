<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    /**
     * Handle logout request
     * Revokes the current access token
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request): Response
    {
        $user = auth()->user();

        if ($user && $user->token()) {
            $user->token()->revoke();
        }

        return response()->json(
            ['message' => 'Successfully logged out'],
            Response::HTTP_OK
        );
    }
}
