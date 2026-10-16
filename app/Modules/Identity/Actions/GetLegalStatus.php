<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;

/**
 * `me.legal`: the current versions, the user's last accepted ones and whether the app must ask
 * again. Children never need to accept (their parent gave parental consent). One query.
 */
class GetLegalStatus
{
    /**
     * @return array{needs_acceptance: bool, terms_version: string, privacy_version: string, accepted_terms_version: string|null, accepted_privacy_version: string|null}
     */
    public function handle(User $user, bool $isChild): array
    {
        $latest = Consent::query()
            ->where('user_id', $user->id)
            ->whereIn('type', [Consent::TERMS, Consent::PRIVACY])
            ->orderByDesc('id')
            ->get(['type', 'version'])
            ->unique('type')
            ->pluck('version', 'type');

        $terms = LegalDocuments::termsVersion();
        $privacy = LegalDocuments::privacyVersion();
        $acceptedTerms = $latest->get(Consent::TERMS);
        $acceptedPrivacy = $latest->get(Consent::PRIVACY);

        return [
            'needs_acceptance' => ! $isChild && ($acceptedTerms !== $terms || $acceptedPrivacy !== $privacy),
            'terms_version' => $terms,
            'privacy_version' => $privacy,
            'accepted_terms_version' => is_string($acceptedTerms) ? $acceptedTerms : null,
            'accepted_privacy_version' => is_string($acceptedPrivacy) ? $acceptedPrivacy : null,
        ];
    }
}
