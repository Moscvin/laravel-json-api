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
        $username = $request->input('username') ?: env('SMART_TENDERING_USER');
        $password = $request->input('password') ?: env('SMART_TENDERING_PASS');

        // Optional override of hostname to resolve theme
        $hostname = $request->input('hostname') ?: 'www.smart-tendering.com';

        // If a static BEARER_TOKEN is provided in .env, return it immediately
        $staticToken = env('BEARER_TOKEN');
        if ($staticToken) {
            return response()->json([
                'access_token' => $staticToken,
                'token_type' => 'Bearer',
                // large expires_in so the frontend treats it as non-expiring
                'expires_in' => 315360000 // ~10 years in seconds
            ]);
        }

        if (! $username || ! $password) {
            return response()->json(['error' => 'username and password required (or set SMART_TENDERING_USER/PASS in .env)'], 422);
        }

        $audience = env('SMART_TENDERING_AUDIENCE', 'https://api.tnx.co.nz');
        $realm = env('SMART_TENDERING_REALM', 'Username-Password-Authentication');

        // First attempt to resolve theme to get auth domain and client id
        $resolveUrl = env('SMART_TENDERING_RESOLVE_URL', 'https://api.tnx.co.nz/v2019.4/resolve_theme');
        try {
            $themeResp = Http::get($resolveUrl, ['hostname' => $hostname]);
        } catch (\Exception $e) {
            Log::warning('SmartTendering resolve_theme error: ' . $e->getMessage());
            $themeResp = null;
        }

        $authDomain = null;
        $clientId = env('SMART_TENDERING_CLIENT_ID');
        if ($themeResp && $themeResp->ok()) {
            $tdata = $themeResp->json('data');
            if (isset($tdata['auth_domain'])) {
                $authDomain = $tdata['auth_domain'];
            }
            if (isset($tdata['auth_client_id'])) {
                $clientId = $tdata['auth_client_id'];
            }
        }

        // Fallback auth domain if not found
        if (! $authDomain) {
            $authDomain = env('SMART_TENDERING_AUTH_DOMAIN', 'tnxnz.au.auth0.com');
        }

        // Build token URL from resolved auth domain
        $authUrl = (strpos($authDomain, 'http') === 0) ? rtrim($authDomain, '/') . '/oauth/token' : 'https://' . rtrim($authDomain, '/') . '/oauth/token';

        if (! $clientId) {
            return response()->json(['error' => 'client_id not available (set SMART_TENDERING_CLIENT_ID or resolve_theme must return auth_client_id)'], 500);
        }

        // If a client secret is provided in env, prefer client_credentials (M2M)
        $clientSecret = env('SMART_TENDERING_CLIENT_SECRET');

        if ($clientSecret) {
            $payload = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
                'audience' => $audience,
            ];
        } else {
            // fallback to resource-owner password (password-realm)
            $payload = [
                'client_id' => $clientId,
                'username' => $username,
                'password' => $password,
                'realm' => $realm,
                'grant_type' => 'http://auth0.com/oauth/grant-type/password-realm',
                'audience' => $audience,
                'scope' => 'openid'
            ];
        }

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
