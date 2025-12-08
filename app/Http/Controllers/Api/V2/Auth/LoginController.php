<?php

namespace App\Http\Controllers\Api\V2\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\Auth\LoginRequest;
use App\Models\User;
use LaravelJsonApi\Core\Document\Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LoginController extends Controller
{
    /**
     * Handle the incoming request.
     * Authenticates user and returns OAuth token
     *
     * @param \App\Http\Requests\Api\V2\Auth\LoginRequest $request
     *
     * @return \Symfony\Component\HttpFoundation\Response|\LaravelJsonApi\Core\Document\Error
     * @throws \Exception
     */
    public function __invoke(LoginRequest $request): Response|Error
    {
        // Check if user is blocked
        $user = User::where('email', $request->email)->first();
        if ($user && $user->isBlocked()) {
            return Error::fromArray([
                'title'  => 'Unauthorized',
                'detail' => 'Your account has been blocked. Please contact support.',
                'status' => Response::HTTP_UNAUTHORIZED,
            ]);
        }

        $client = DB::table('oauth_clients')->where('password_client', 1)->first();

        $oauthRequest = Request::create(config('app.url') . '/oauth/token', 'POST', [
            'grant_type'    => 'password',
            'client_id'     => $client->id,
            'client_secret' => $client->secret,
            'username'      => $request->email,
            'password'      => $request->password,
            'scope'         => '',
        ]);

        /** @var \Illuminate\Http\Response $response */
        $response = app()->handle($oauthRequest);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return Error::fromArray([
                'title'  => Response::$statusTexts[Response::HTTP_BAD_REQUEST],
                'detail' => 'Invalid email or password',
                'status' => Response::HTTP_BAD_REQUEST,
            ]);
        }

        // Update last active timestamp on successful login
        if ($user) {
            $user->updateLastActive();
        }

        return $response;
    }
}
