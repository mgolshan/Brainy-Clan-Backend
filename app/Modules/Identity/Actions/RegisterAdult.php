<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Self sign-up with email + password (no invite). Refused (422) below the self sign-up age
 * (`PolicyResolver::selfSignUpAllowed()`: `family.owner_min_age`, 16, unless the band collects email),
 * so a 16- or 17-year-old can register and create a family; younger children and teens are invited.
 */
class RegisterAdult
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly IssueDeviceToken $issueToken,
        private readonly AcceptLegalTerms $acceptLegalTerms,
    ) {}

    /**
     * The request already checked `accept_terms`; the current terms + privacy versions are recorded
     * as consents with the user.
     *
     * @param  array{display_name: string, email: string, password: string, birth_year: int, birth_month: int, locale?: string|null}  $data
     * @return array{user: User, token: string}
     */
    public function handle(array $data, string $deviceName, string $platform, ?string $appVersion = null, ?string $ipAddress = null): array
    {
        if (! $this->policy->selfSignUpAllowed((int) $data['birth_year'], (int) $data['birth_month'])) {
            $minAge = $this->policy->familyOwnerMinAge();

            throw ApiException::validation('birth_year', "You need to be at least {$minAge} to create an account. Ask a parent to invite you to the family.");
        }

        return DB::transaction(function () use ($data, $deviceName, $platform, $appVersion, $ipAddress): array {
            $user = User::query()->create([
                'display_name' => $data['display_name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'birth_year' => (int) $data['birth_year'],
                'birth_month' => (int) $data['birth_month'],
                'locale' => $data['locale'] ?? 'en',
            ]);

            $this->acceptLegalTerms->handle($user, $ipAddress);

            return ['user' => $user, 'token' => $this->issueToken->handle($user, $deviceName, $platform, $appVersion)];
        });
    }
}
