<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\Invite;
use App\Modules\Identity\Actions\RecordConsent;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\Consent;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * An admin invites a new member (nickname + birth data). Refused with 403 `member_limit_reached`
 * when active members + pending invites already fill the tier's member limit, and for an adult
 * (band with `purchases_allowed`) with 403 `adult_limit_reached` when active adults + pending adult
 * invites already fill the tier's `limits.max_adults` (null = unlimited). Members over a limit
 * after a plan change keep their seats. For a child (band with `is_child`) the admin must give
 * parental consent (422 on `parental_consent` otherwise), recorded as a `parental` consent for the
 * invite (privacy policy version).
 */
class CreateInvite
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly GenerateInviteCode $generateCode,
        private readonly RecordConsent $recordConsent,
    ) {}

    /**
     * @param  array{nickname: string, birth_year: int, birth_month: int, avatar?: string|null, parental_consent?: bool|null}  $data
     */
    public function handle(FamilyMember $admin, array $data, ?string $ipAddress = null): Invite
    {
        $family = $admin->family;
        $band = $this->policy->bandForBirth((int) $data['birth_year'], (int) $data['birth_month'], $family->country_code);
        $isChild = $this->policy->isChildBand($band);
        $isAdult = $this->policy->isAdultBand($band);

        if ($isChild && ($data['parental_consent'] ?? false) !== true) {
            throw ApiException::validation('parental_consent', 'Please confirm that you are the parent or guardian (or have their permission) and agree to the privacy policy for this child.');
        }

        return DB::transaction(function () use ($admin, $family, $data, $isChild, $isAdult, $ipAddress): Invite {
            $used = $family->members()->count() + $family->pendingInvites()->count();

            if ($used >= $this->policy->maxMembers($family)) {
                throw ApiException::memberLimitReached();
            }

            $maxAdults = $this->policy->maxAdultsFor($family->tier);

            if ($isAdult && $maxAdults !== null && $this->adultSeatsUsed($family) >= $maxAdults) {
                throw ApiException::adultLimitReached();
            }

            $invite = Invite::query()->create([
                'family_id' => $family->id,
                'code' => $this->generateCode->handle(),
                'created_by' => $admin->user_id,
                'nickname' => $data['nickname'],
                'avatar' => $data['avatar'] ?? null,
                'birth_year' => (int) $data['birth_year'],
                'birth_month' => (int) $data['birth_month'],
                'expires_at' => now()->addDays($this->policy->inviteTtlDays()),
            ]);

            if ($isChild) {
                $this->recordConsent->handle($admin->user, Consent::PARENTAL, LegalDocuments::privacyVersion(), $ipAddress, subjectInviteId: $invite->id);
            }

            return $invite;
        });
    }

    /**
     * Active adult members + pending adult invites, in two count queries: an adult is anyone at
     * least `adultMinAge()` old today; a user without birth data resolves to the adult band (like
     * `PolicyResolver::bandForAge()`).
     */
    private function adultSeatsUsed(Family $family): int
    {
        $minAge = $this->policy->adultMinAge($family->country_code);

        if ($minAge === null) {
            return 0;
        }

        $adultMembers = $family->members()
            ->whereHas('user', fn (Builder $query) => $query->where(
                fn (Builder $query) => $this->bornAtLeastYearsAgo($query->whereNull('birth_year'), $minAge, 'or'),
            ))
            ->count();

        $adultInvites = $this->bornAtLeastYearsAgo($family->pendingInvites()->getQuery(), $minAge)->count();

        return $adultMembers + $adultInvites;
    }

    /**
     * Birth year/month at least `$years` ago this month (the birthday counts as passed within the
     * birth month; a missing month counts as January), i.e. `PolicyResolver::age() >= $years`.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private function bornAtLeastYearsAgo(Builder $query, int $years, string $boolean = 'and'): Builder
    {
        $year = now()->year - $years;
        $month = now()->month;

        return $query->where(fn (Builder $query) => $query
            ->where('birth_year', '<', $year)
            ->orWhere(fn (Builder $query) => $query->where('birth_year', $year)->whereRaw('coalesce(birth_month, 1) <= ?', [$month])), boolean: $boolean);
    }
}
