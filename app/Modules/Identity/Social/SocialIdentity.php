<?php

namespace App\Modules\Identity\Social;

/**
 * Who a verified Google / Facebook token belongs to. `email` is only set when the provider
 * vouches for it (Google `email_verified`; Facebook only returns confirmed emails) and may be
 * null (e.g. a phone-only Facebook account). `name` is the first name (the default
 * `display_name` of a new user), falling back to the full name.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public ?string $email,
        public ?string $name,
    ) {}
}
