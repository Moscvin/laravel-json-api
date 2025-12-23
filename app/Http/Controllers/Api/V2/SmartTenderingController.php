<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\LoadSmart;
use Carbon\Carbon;

class SmartTenderingController extends Controller
{
    /**
     * Obține token de la Auth0
     */
    public function token(Request $request)
    {
        $username = $request->input('username', 'ted@bvbfreight.com');
        $password = $request->input('password', 'Devveloper@2025');
        // Production client_id and audience
        $clientId = 'sC83pbj4C3kjlFJTuukNV6OKZ76ltqf9';
        $realm = 'Username-Password-Authentication';
        $grantType = 'http://auth0.com/oauth/grant-type/password-realm';
        $audience = 'https://api.tnx.co.nz';
        $scope = 'openid';
        $authUrl = 'https://tnxnz.au.auth0.com/oauth/token';

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

        $endpoint = $request->input('endpoint', 'https://api.tnx.co.nz/v2016.7/users/me');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
        // Adaugă x-tnx-auth0-tenant și x-tnx-org dacă sunt prezente
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

        // Preluăm toți parametrii query din request, fără endpoint/x-tnx-org duplicat
        $endpoint = $request->input('endpoint', 'https://api.tnx.co.nz/v2019.4/orders/tenders');
        $queryParams = $request->except(['endpoint', 'x-tnx-org']);

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
        // Adaugă x-tnx-org dacă este prezent în request (conform doc TNX)
        if ($request->hasHeader('x-tnx-org')) {
            $headers['x-tnx-org'] = $request->header('x-tnx-org');
        } elseif ($request->has('x-tnx-org')) {
            $headers['x-tnx-org'] = $request->input('x-tnx-org');
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

            $tenders = $resp->json();
            if (is_array($tenders)) {
                foreach ($tenders as $tender) {
                    $this->saveTenderToLoadSmart($tender);
                }
            } elseif (is_array($tenders) && count($tenders) === 0) {
                // No tenders
            } else {
                // Single tender
                $this->saveTenderToLoadSmart($tenders);
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

    private function saveTenderToLoadSmart($tender)
    {
        $idLoadSmart = $tender['uid'] ?? null;
        if (!$idLoadSmart) return;

        $measureName = $tender['max_measures'][0]['id'] ?? null;
        $measureValue = $tender['max_measures'][0]['value'] ?? null;

        $shortAddress1 = $tender['trip']['activities'][0]['end_state']['location']['short_address'] ?? null;
        $shortAddress2 = $tender['trip']['activities'][1]['end_state']['location']['short_address'] ?? null;

        $hourAddress1Str = $tender['trip']['activities'][0]['timing']['earliest_start'] ?? null;
        $hourAddress2Str = $tender['trip']['activities'][1]['timing']['earliest_start'] ?? null;

        $timezone1 = $tender['trip']['activities'][0]['end_state']['location']['timezone'] ?? 'UTC';
        $timezone2 = $tender['trip']['activities'][1]['end_state']['location']['timezone'] ?? 'UTC';

        $hourAddress1 = $hourAddress1Str ? Carbon::parse($hourAddress1Str)->setTimezone($timezone1)->format('m/d/Y,g:i A') : null;
        $hourAddress2 = $hourAddress2Str ? Carbon::parse($hourAddress2Str)->setTimezone($timezone2)->format('m/d/Y,g:i A') : null;

        $type = $tender['cargos'][0]['type'] ?? null;
        $bidAmount = $tender['current_reply']['bid_amount'] ?? null;
        $matchPrice = $tender['match_price'] ?? null;

        LoadSmart::updateOrCreate(
            ['id_load_smart' => $idLoadSmart],
            [
                'measure_name' => $measureName,
                'measure_value' => $measureValue,
                'short_address_1' => $shortAddress1,
                'hour_address_1' => $hourAddress1,
                'short_address_2' => $shortAddress2,
                'hour_address_2' => $hourAddress2,
                'type' => $type,
                'bid_amount' => $bidAmount,
                'match_price' => $matchPrice,
            ]
        );
    }
}
