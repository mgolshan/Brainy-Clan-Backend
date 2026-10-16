<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * A user accepts the current terms of service and privacy policy: one `terms` and one `privacy`
 * consent row. Used by sign-ups (register, social sign-up, adult join with email) and by
 * `POST /me/legal-acceptance` after a version change.
 */
class AcceptLegalTerms
{
    public function __construct(
        private readonly RecordConsent $record,
        private readonly PolicyResolver $policy,
    ) {}

    /**
     * Records the current versions (the caller checked `accept_terms`).
     */
    public function handle(User $user, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($user, $ipAddress): void {
            $this->record->handle($user, Consent::TERMS, LegalDocuments::termsVersion(), $ipAddress);
            $this->record->handle($user, Consent::PRIVACY, LegalDocuments::privacyVersion(), $ipAddress);
        });
    }

    /**
     * `POST /me/legal-acceptance`: the versions the app showed must be the current ones (422 on
     * the stale field otherwise, so the app reloads `GET /legal`). Children get 403 `forbidden`:
     * their parent consented for them.
     */
    public function confirm(User $user, string $termsVersion, string $privacyVersion, ?string $ipAddress = null): void
    {
        if ($this->policy->isChildUser($user)) {
            throw ApiException::forbidden('Your parent already agreed for you.');
        }

        if ($termsVersion !== LegalDocuments::termsVersion()) {
            throw ApiException::validation('terms_version', 'These terms are not the current version. Please reload them.');
        }

        if ($privacyVersion !== LegalDocuments::privacyVersion()) {
            throw ApiException::validation('privacy_version', 'This privacy policy is not the current version. Please reload it.');
        }

        $this->handle($user, $ipAddress);
    }
}
