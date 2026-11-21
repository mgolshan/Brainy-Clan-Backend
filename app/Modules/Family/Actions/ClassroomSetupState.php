<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use Illuminate\Database\Eloquent\Builder;

/**
 * Classroom setup state (docs/API.md "Classroom tools" → "Setup state"): the Family object's
 * `classroom` field, and the gates of the Classroom tools.
 *
 * - `consent_version` / `consent_accepted_at`: the newest classroom consent of an **active admin**
 *   of the family (teacher or co-teacher), any version; the app compares the version with
 *   `GET /legal` → `classroom_consent.version`.
 * - The tools need an active admin's consent of the **current** version
 *   (`LegalDocuments::classroomConsentVersion()`); a stale version, or a consent by someone who is
 *   no longer an admin, does not count.
 * - `students_count` is the number of active non-admin members.
 *
 * Two queries for a Classroom family (consent, students), none for other tiers.
 */
class ClassroomSetupState
{
    /**
     * @return array{consent_version: string|null, consent_accepted_at: string|null, students_count: int, codes_issued_at: string|null, season_started_at: string|null}|null
     */
    public function forFamily(Family $family): ?array
    {
        if ($family->tier !== Family::TIER_CLASSROOM) {
            return null;
        }

        /** @var Consent|null $consent */
        $consent = $this->adminConsents($family)->first();

        return [
            'consent_version' => $consent?->version,
            'consent_accepted_at' => Iso::format($consent?->accepted_at),
            'students_count' => $family->members()->where('role', FamilyMember::ROLE_MEMBER)->count(),
            'codes_issued_at' => Iso::format($family->codes_issued_at),
            'season_started_at' => Iso::format($family->season_started_at),
        ];
    }

    /**
     * Whether an active admin of the family accepted the current consent version.
     */
    public function hasCurrentConsent(Family $family): bool
    {
        return $this->adminConsents($family)->where('version', LegalDocuments::classroomConsentVersion())->exists();
    }

    /**
     * Gate of every Classroom tool: 403 `plan_required` on any other tier, then (when
     * `$requireConsent`) 409 `classroom_consent_required` until an admin accepted the current consent.
     */
    public function ensure(Family $family, bool $requireConsent = true): void
    {
        if ($family->tier !== Family::TIER_CLASSROOM) {
            throw ApiException::planRequired('Classroom tools need the Classroom plan.');
        }

        if ($requireConsent && ! $this->hasCurrentConsent($family)) {
            throw ApiException::classroomConsentRequired();
        }
    }

    /**
     * Classroom consents of the family's active admins, newest first.
     *
     * @return Builder<Consent>
     */
    private function adminConsents(Family $family): Builder
    {
        return Consent::query()
            ->where('type', Consent::CLASSROOM)
            ->whereIn('user_id', FamilyMember::query()
                ->select('user_id')
                ->where('family_id', $family->id)
                ->where('status', FamilyMember::STATUS_ACTIVE)
                ->where('role', FamilyMember::ROLE_ADMIN))
            ->orderByDesc('accepted_at')
            ->orderByDesc('id');
    }
}
