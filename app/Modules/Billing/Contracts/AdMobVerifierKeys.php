<?php

namespace App\Modules\Billing\Contracts;

/**
 * Google's public keys for AdMob rewarded-ad server-side verification. Faked in tests.
 */
interface AdMobVerifierKeys
{
    /**
     * Key id => PEM public key (ECDSA P-256). Cached; `$refresh` refetches (unknown key id after rotation).
     *
     * @return array<string, string>
     */
    public function keys(bool $refresh = false): array;
}
