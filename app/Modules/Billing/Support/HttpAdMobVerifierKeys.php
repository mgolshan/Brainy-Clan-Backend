<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\AdMobVerifierKeys;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches `{services.admob.verifier_keys_url}` (`{ "keys": [{ "keyId": 3335741209, "pem": "…" }] }`)
 * and caches the key map for a day. A failed fetch is not cached.
 */
class HttpAdMobVerifierKeys implements AdMobVerifierKeys
{
    public const string CACHE_KEY = 'billing:admob-ssv-keys';

    public const int CACHE_SECONDS = 86400;

    public function keys(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        /** @var array<string, string>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $keys = $this->fetch();

        if ($keys !== []) {
            Cache::put(self::CACHE_KEY, $keys, self::CACHE_SECONDS);
        }

        return $keys;
    }

    /**
     * @return array<string, string>
     */
    private function fetch(): array
    {
        try {
            $response = Http::acceptJson()->timeout(10)->retry(2, 300, throw: false)->get((string) config('services.admob.verifier_keys_url'));
        } catch (ConnectionException $exception) {
            Log::warning('AdMob verifier keys request failed', ['error' => $exception->getMessage()]);

            return [];
        }

        if ($response->failed()) {
            Log::warning('AdMob verifier keys request failed', ['status' => $response->status()]);

            return [];
        }

        $keys = [];

        foreach ((array) $response->json('keys', []) as $key) {
            if (is_array($key) && is_scalar($key['keyId'] ?? null) && is_string($key['pem'] ?? null)) {
                $keys[(string) $key['keyId']] = $key['pem'];
            }
        }

        return $keys;
    }
}
