<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Events\MemberJoined;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * `POST /family/classroom/roster` (admin, Classroom, consent given; docs/API.md "Classroom tools" →
 * "Roster"): adds 1–35 students in one go, without invites.
 *
 * - Each student becomes an active member with role `member` and a user without email or password
 *   (like a child who joined by invite), so they sign in with a code card only.
 * - Birth year: the student's own, else the class-wide `birth_year`; the month is June (mid-year).
 * - Nicknames are unique in the family (active members, case-insensitive) and within the request:
 *   422 on `students.N.nickname`.
 * - 403 `member_limit_reached` when active members + pending invites + the new students pass the
 *   tier's `max_members`; 403 `adult_limit_reached` when students old enough to be adults would pass
 *   `max_adults` (the teacher's consent statement covers the students: no `parental_consent`).
 * - `MemberJoined` per student (Goals adds them to `everyone` plans), inside one transaction that
 *   locks the family row, so two rosters sent at once cannot pass the limit.
 */
class AddClassroomRoster
{
    /** Birth month used for every roster student (mid-year: the day and month are never asked). */
    public const int BIRTH_MONTH = 6;

    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly CountAdultSeats $adultSeats,
    ) {}

    /**
     * @param  array{birth_year: int, students: list<array{nickname: string, birth_year?: int|null}>}  $data
     * @return Collection<int, FamilyMember>
     */
    public function handle(FamilyMember $admin, array $data): Collection
    {
        $students = array_map(fn (array $student): array => [
            'nickname' => trim($student['nickname']),
            'birth_year' => (int) ($student['birth_year'] ?? $data['birth_year']),
        ], $data['students']);

        return DB::transaction(function () use ($admin, $students): Collection {
            /** @var Family $family */
            $family = Family::query()->lockForUpdate()->findOrFail($admin->family_id);

            $this->ensureUniqueNicknames($family, $students);

            $used = $family->members()->count() + $family->pendingInvites()->count();

            if ($used + count($students) > $this->policy->maxMembers($family)) {
                throw ApiException::memberLimitReached();
            }

            $newAdults = count(array_filter($students, fn (array $student): bool => $this->policy->isAdultBand(
                $this->policy->bandForBirth($student['birth_year'], self::BIRTH_MONTH, $family->country_code),
            )));
            $maxAdults = $this->policy->maxAdultsFor($family->tier);

            if ($newAdults > 0 && $maxAdults !== null && $this->adultSeats->handle($family) + $newAdults > $maxAdults) {
                throw ApiException::adultLimitReached();
            }

            $locale = in_array($admin->user->locale, User::LOCALES, true) ? $admin->user->locale : 'en';
            $members = new Collection;

            foreach ($students as $student) {
                $user = User::query()->create([
                    'display_name' => $student['nickname'],
                    'avatar' => null,
                    'email' => null,
                    'password' => null,
                    'birth_year' => $student['birth_year'],
                    'birth_month' => self::BIRTH_MONTH,
                    'locale' => $locale,
                ]);
                // No email and no social account yet: `has_own_login` is false without a query.
                $user->setRelation('socialAccounts', new Collection);

                $member = FamilyMember::query()->create([
                    'family_id' => $family->id,
                    'user_id' => $user->id,
                    'role' => FamilyMember::ROLE_MEMBER,
                    'nickname' => $student['nickname'],
                    'status' => FamilyMember::STATUS_ACTIVE,
                    'joined_at' => now(),
                ]);
                $member->setRelation('user', $user)->setRelation('family', $family);

                MemberJoined::dispatch($member->id, $family->id);

                $members->push($member);
            }

            return $members;
        });
    }

    /**
     * @param  list<array{nickname: string, birth_year: int}>  $students
     */
    private function ensureUniqueNicknames(Family $family, array $students): void
    {
        $taken = $family->members()->pluck('nickname')
            ->map(fn ($nickname) => mb_strtolower((string) $nickname))
            ->flip()
            ->all();

        foreach ($students as $index => $student) {
            $key = mb_strtolower($student['nickname']);

            if (isset($taken[$key])) {
                throw ApiException::validation("students.{$index}.nickname", 'Someone in this group already uses this nickname.');
            }

            $taken[$key] = true;
        }
    }
}
