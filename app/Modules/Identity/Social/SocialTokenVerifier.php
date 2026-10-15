<?php

namespace App\Modules\Identity\Social;

use App\Modules\Core\Exceptions\ApiException;

/**
 * Verifies, server-side, a token the app got from a provider's native sign-in.
 * Tests bind a fake implementation (see `SocialTokenVerifiers`).
 */
interface SocialTokenVerifier
{
    /**
     * @throws ApiException `social_token_invalid` (401) when the token can't be verified
     */
    public function verify(string $token, ?string $nonce = null): SocialIdentity;
}
