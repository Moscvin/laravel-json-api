<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class SmartTenderingController extends Controller
{
    /**
     * Obține token de la Auth0
     */
    public function token(Request $request)
    {
        $username = $request->input('username', 'ted@bvbfreight.com');
        $password = $request->input('password', 'Ddeveloper@2025');
        $clientId = 'T8wRCMxqyBJHNkiF71yAKDGfsG5tmcSe';
        $realm = 'Username-Password-Authentication';
        $grantType = 'http://auth0.com/oauth/grant-type/password-realm';

        // Verifică ce API vrei să folosești:
        // Pentru test: 'https://api.test.transport-ninja.com'
        // Pentru producție: trebuie să afli audience-ul corect pentru api.tnx.co.nz
        $audience = 'https://api.test.transport-ninja.com';

        $scope = 'openid';
        $authUrl = 'https://transport-ninja.auth0.com/oauth/token';

        $payload = [
            'client_id' => $clientId,
            'username' => $username,
            'password' => $password,
            'realm' => $realm,
            'grant_type' => $grantType,
            'audience' => $audience,
            'scope' => $scope,
        ];

        try {
            $resp = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post($authUrl, $payload);
        } catch (\Exception $e) {
            Log::error('SmartTendering token proxy error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Token request failed',
                'detail' => $e->getMessage()
            ], 502);
        }

        if ($resp->failed()) {
            Log::warning('SmartTendering token proxy failed: ' . $resp->body());
            return response()->json([
                'error' => 'Auth provider error',
                'detail' => $resp->json()
            ], $resp->status());
        }

        return response()->json($resp->json());
    }

    /**
     * Proxy pentru /users/me
     */
    public function getMe(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Missing authorization token'], 401);
        }

        try {
            // Folosește API-ul test care corespunde cu audience-ul din token
            $resp = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(30)->get('https://api.test.transport-ninja.com/v2016.7/users/me');

            if ($resp->failed()) {
                return response()->json([
                    'error' => 'Failed to fetch user info',
                    'detail' => $resp->json()
                ], $resp->status());
            }

            return response()->json($resp->json());
        } catch (\Exception $e) {
            Log::error('SmartTendering getMe proxy error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Request failed',
                'detail' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * Proxy pentru /orders/tenders
     */
    public function getTenders(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Missing authorization token'], 401);
        }


        // Preluăm toți parametrii query din request
        $queryParams = $request->all();
        // Nu mai acceptăm size=all, doar valoarea numerică transmisă de client

        try {
            // Folosește API-ul test care corespunde cu audience-ul din token
            $resp = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->timeout(30)->get('https://api.test.transport-ninja.com/v2019.4/orders/tenders', $queryParams);

            if ($resp->failed()) {
                return response()->json([
                    'error' => 'Failed to fetch tenders',
                    'detail' => $resp->json()
                ], $resp->status());
            }

            return response()->json($resp->json());
        } catch (\Exception $e) {
            Log::error('SmartTendering getTenders proxy error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Request failed',
                'detail' => $e->getMessage()
            ], 502);
        }
    }

    /**
     * Proxy generic pentru orice endpoint TNX (opțional, pentru flexibilitate)
     */
    public function proxyRequest(Request $request, $path)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Missing authorization token'], 401);
        }

        $method = strtolower($request->method());
        // Folosește API-ul test care corespunde cu audience-ul din token
        $url = 'https://api.test.transport-ninja.com/' . $path;
        $queryParams = $request->query();
        $bodyData = $request->all();

        try {
            $httpRequest = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30);

            $resp = match ($method) {
                'get' => $httpRequest->get($url, $queryParams),
                'post' => $httpRequest->post($url, $bodyData),
                'put' => $httpRequest->put($url, $bodyData),
                'patch' => $httpRequest->patch($url, $bodyData),
                'delete' => $httpRequest->delete($url, $bodyData),
                default => throw new \Exception("Unsupported HTTP method: {$method}")
            };

            if ($resp->failed()) {
                return response()->json([
                    'error' => 'Proxy request failed',
                    'detail' => $resp->json()
                ], $resp->status());
            }

            return response()->json($resp->json());
        } catch (\Exception $e) {
            Log::error("SmartTendering proxy error [{$method} {$url}]: " . $e->getMessage());
            return response()->json([
                'error' => 'Proxy request failed',
                'detail' => $e->getMessage()
            ], 502);
        }
    }
}
