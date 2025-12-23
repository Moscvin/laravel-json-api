<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\LoadSmart;
use Carbon\Carbon;

class LoadSmartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:loadsmart {--username=ted@bvbfreight.com : Username for token} {--password=Devveloper@2025 : Password for token} {--org= : x-tnx-org header}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import load smart data: authenticate, verify user, fetch tenders and save to load_smart table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->option('username');
        $password = $this->option('password');
        $org = $this->option('org');

        $this->info('Getting bearer token...');

        $token = $this->getBearerToken($username, $password);
        if (!$token) {
            $this->error('Failed to get bearer token.');
            return 1;
        }

        $this->info('Verifying user with /users/me...');

        if (!$this->verifyUser($token, $org)) {
            $this->error('User verification failed.');
            return 1;
        }

        $endpoint = 'https://api.tnx.co.nz/v2019.4/orders/tenders';

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        if ($org) {
            $headers['x-tnx-org'] = $org;
        }

        $this->info('Fetching tenders from API...');

        try {
            $count = 0;
            $continuationKey = null;

            do {
                $query = [
                    'status' => 'CREATED,VALIDATED,AUCTION_ASSIGNED',
                    'has_own_bid' => 'true',
                    'sort_type' => 'time_created.desc',
                ];
                if ($continuationKey) {
                    $query['continuation_key'] = $continuationKey;
                }

                $resp = Http::withHeaders($headers)
                    ->timeout(30)
                    ->get('https://api.tnx.co.nz/v2019.4/orders/tenders', $query);

                if ($resp->failed()) {
                    $this->error('Failed to fetch tenders: ' . $resp->body());
                    return 1;
                }

                $tenders = $resp->json();
                Log::info('API Response keys: ' . json_encode(array_keys($tenders)));
                $items = $tenders['data']['items'] ?? $tenders['items'] ?? [];

                foreach ($items as $tender) {
                    $this->saveTenderToLoadSmart($tender);
                    $count++;
                }

                $continuationKey = $tenders['data']['meta']['continuation_key'] ?? $tenders['meta']['continuation_key'] ?? null;
                Log::info('Continuation key: ' . ($continuationKey ?: 'null'));
            } while ($continuationKey);

            $this->info("Processed {$count} tenders.");
            return 0;
        } catch (\Exception $e) {
            $this->error('Error fetching tenders: ' . $e->getMessage());
            Log::error('FetchTendersCommand error: ' . $e->getMessage());
            return 1;
        }
    }

    private function verifyUser($token, $org)
    {
        $endpoint = 'https://api.tnx.co.nz/v2016.7/users/me';

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        if ($org) {
            $headers['x-tnx-org'] = $org;
        }

        try {
            $resp = Http::withHeaders($headers)
                ->timeout(30)
                ->get($endpoint);

            return $resp->successful();
        } catch (\Exception $e) {
            $this->error('User verification error: ' . $e->getMessage());
            return false;
        }
    }

    private function getBearerToken($username, $password)
    {
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

            if ($resp->failed()) {
                $this->error('Token request failed: ' . $resp->body());
                return null;
            }

            $data = $resp->json();
            return $data['access_token'] ?? null;
        } catch (\Exception $e) {
            $this->error('Token request error: ' . $e->getMessage());
            return null;
        }
    }

    private function saveTenderToLoadSmart($tender)
    {
        $idLoadSmart = $tender['uid'] ?? null;
        if (!$idLoadSmart) {
            Log::warning('Skipping tender without uid');
            return;
        }

        // Check if tender already exists
        if (LoadSmart::where('id_load_smart', $idLoadSmart)->exists()) {
            Log::info('Skipping existing tender with uid: ' . $idLoadSmart);
            return;
        }

        Log::info('Processing tender with uid: ' . $idLoadSmart);

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
        $status = $tender['status'] ?? null;

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
                'status' => $status,
            ]
        );
    }
}
