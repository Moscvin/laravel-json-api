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
        $password = $request->input('password', 'Devveloper@2025');
        $clientId = $request->input('client_id', 'T8wRCMxqyBJHNkiF71yAKDGfsG5tmcSe');
        $realm = $request->input('realm', 'Username-Password-Authentication');
        $grantType = $request->input('grant_type', 'http://auth0.com/oauth/grant-type/password-realm');
        $audience = $request->input('audience', 'https://api.test.transport-ninja.com');
        $scope = $request->input('scope', 'openid');
        $authUrl = $request->input('auth_url', 'https://transport-ninja.auth0.com/oauth/token');

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

        // Default: endpoint de TEST
        $endpoint = $request->input('endpoint', 'https://api.test.transport-ninja.com/v2016.7/users/me');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
        // Permite headere custom pentru test (ex: x-tnx-auth0-tenant, x-tnx-org)
        foreach (
            [
                'x-tnx-auth0-tenant',
                'x-tnx-org',
            ] as $h
        ) {
            if ($request->hasHeader($h)) {
                $headers[$h] = $request->header($h);
            } elseif ($request->has($h)) {
                $headers[$h] = $request->input($h);
            }
        }

        try {
            $resp = Http::withHeaders($headers)
                ->timeout(30)
                ->get($endpoint);

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

        // Default: endpoint de TEST
        $endpoint = $request->input('endpoint', 'https://api.test.transport-ninja.com/v2019.4/orders/tenders');
        $queryParams = $request->except(['endpoint', 'x-tnx-auth0-tenant', 'x-tnx-org']);
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
        foreach (
            [
                'x-tnx-auth0-tenant',
                'x-tnx-org',
            ] as $h
        ) {
            if ($request->hasHeader($h)) {
                $headers[$h] = $request->header($h);
            } elseif ($request->has($h)) {
                $headers[$h] = $request->input($h);
            }
        }

        try {
            $resp = Http::withHeaders($headers)
                ->timeout(30)
                ->get($endpoint, $queryParams);

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
        $url = 'https://api.tnx.co.nz/' . $path;
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
