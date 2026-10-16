<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Contracts\AdMobVerifierKeys;

/**
 * Verifies an AdMob server-side verification callback: the signed content is the raw query string
 * up to (not including) `&signature=`; `signature` (web-safe base64, DER ECDSA SHA-256) and
 * `key_id` are always the last two parameters. Verified with openssl against Google's key PEM;
 * an unknown `key_id` refetches the keys once (rotation).
 */
class AdMobSignatureVerifier
{
    public function __construct(private readonly AdMobVerifierKeys $keys) {}

    public function verify(string $rawQuery): bool
    {
        $position = strpos($rawQuery, '&signature=');

        if ($position === false || $position === 0) {
            return false;
        }

        $message = substr($rawQuery, 0, $position);
        parse_str(substr($rawQuery, $position + 1), $tail);
        $signature = is_string($tail['signature'] ?? null) ? self::base64UrlDecode($tail['signature']) : null;
        $keyId = is_scalar($tail['key_id'] ?? null) ? (string) $tail['key_id'] : '';

        if ($signature === null || $signature === '' || $keyId === '') {
            return false;
        }

        $pem = $this->keys->keys()[$keyId] ?? $this->keys->keys(refresh: true)[$keyId] ?? null;

        if ($pem === null) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($pem);

        return $publicKey !== false && openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
