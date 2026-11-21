<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\RecordConsent;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;

/**
 * `POST /family/classroom/consent` (admin, Classroom; docs/API.md "Classroom tools" → "Consent
 * statement"): the teacher confirms they are authorised to add the students. Stored per user as an
 * append-only `classroom` consent row (version, time, hashed IP). The version must be the current
 * one (422 on `version` otherwise). Accepting a version the same user already accepted adds no
 * second row.
 */
class AcceptClassroomConsent
{
    public function __construct(private readonly RecordConsent $recordConsent) {}

    public function handle(FamilyMember $admin, string $version, ?string $ipAddress = null): Family
    {
        $current = LegalDocuments::classroomConsentVersion();

        if ($version !== $current) {
            throw ApiException::validation('version', 'This consent statement has changed. Please read the current version and accept it.');
        }

        $alreadyAccepted = Consent::query()
            ->where('user_id', $admin->user_id)
            ->where('type', Consent::CLASSROOM)
            ->where('version', $current)
            ->exists();

        if (! $alreadyAccepted) {
            $this->recordConsent->handle($admin->user, Consent::CLASSROOM, $current, $ipAddress);
        }

        return $admin->family;
    }
}
