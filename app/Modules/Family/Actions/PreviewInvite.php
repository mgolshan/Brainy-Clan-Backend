<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\Family;
use App\Modules\Policy\PolicyResolver;

/**
 * `GET /family/invites/preview`: the age band an invitee with this birth year/month would get in
 * this family (country-specific bands apply), and whether parental consent is needed for them.
 */
class PreviewInvite
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @return array{age_band: string, is_child: bool, parental_consent_required: bool, email_login_allowed: bool}
     */
    public function handle(Family $family, int $birthYear, int $birthMonth): array
    {
        $band = $this->policy->bandForBirth($birthYear, $birthMonth, $family->country_code);
        $isChild = $this->policy->isChildBand($band);

        return [
            'age_band' => $band->code ?? 'adult',
            'is_child' => $isChild,
            'parental_consent_required' => $isChild,
            'email_login_allowed' => $this->policy->emailLoginAllowed($band),
        ];
    }
}
