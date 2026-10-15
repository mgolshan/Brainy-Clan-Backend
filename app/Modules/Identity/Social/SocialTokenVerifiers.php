<?php

namespace App\Modules\Identity\Social;

use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Provider key (`google` | `facebook` | `apple`) => its token verifier, resolved from the container so
 * tests can swap one in with `$this->app->instance(GoogleIdTokenVerifier::class, $fake)`.
 */
class SocialTokenVerifiers
{
    /**
     * @var array<string, class-string<SocialTokenVerifier>>
     */
    public const array VERIFIERS = [
        SocialAccount::GOOGLE => GoogleIdTokenVerifier::class,
        SocialAccount::FACEBOOK => FacebookTokenVerifier::class,
        SocialAccount::APPLE => AppleIdTokenVerifier::class,
    ];

    public function __construct(private readonly Container $container) {}

    public function for(string $provider): SocialTokenVerifier
    {
        $class = self::VERIFIERS[$provider] ?? throw new InvalidArgumentException("Unknown social provider [{$provider}].");

        /** @var SocialTokenVerifier $verifier */
        $verifier = $this->container->make($class);

        return $verifier;
    }
}
