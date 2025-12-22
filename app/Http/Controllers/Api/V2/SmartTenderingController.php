<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class SmartTenderingController extends Controller
{
    public function token(Request $request)
    {
        // Folosește datele din curl-ul furnizat
        $username = $request->input('username', 'ted@bvbfreight.com');
        $password = $request->input('password', 'Ddeveloper@2025');
        $clientId = 'T8wRCMxqyBJHNkiF71yAKDGfsG5tmcSe';
        $realm = 'Username-Password-Authentication';
        $grantType = 'http://auth0.com/oauth/grant-type/password-realm';
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
            $resp = Http::withHeaders(['Content-Type' => 'application/json'])->post($authUrl, $payload);
        } catch (\Exception $e) {
            Log::error('SmartTendering token proxy error: ' . $e->getMessage());
            return response()->json(['error' => 'Token request failed', 'detail' => $e->getMessage()], 502);
        }

        if ($resp->failed()) {
            Log::warning('SmartTendering token proxy failed: ' . $resp->body());
            return response()->json(['error' => 'Auth provider error', 'detail' => $resp->body()], $resp->status());
        }

        return response()->json($resp->json());
    }
}
