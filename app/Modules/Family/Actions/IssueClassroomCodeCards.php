<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Models\SignInCode;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * `POST /family/classroom/sign-in-codes` (admin, Classroom, consent given; docs/API.md "Classroom
 * tools" → "Code cards"): one printable sign-in code per member.
 *
 * - The codes are the normal one-time sign-in codes (`POST /auth/code`) but last the tier setting
 *   `sign_in_codes.card_ttl_days` (14) instead of `sign_in_codes.ttl_minutes`.
 * - Default members: every active non-admin member; `member_ids` picks active members of the
 *   family (any other id → 422 on `member_ids`).
 * - Issuing voids the members' older unused codes (one UPDATE), and the new codes are written in one
 *   INSERT, so the number of queries does not grow with the class. Only HMACs are stored.
 * - Sets `families.codes_issued_at` when at least one code was issued.
 *
 * @phpstan-type CodeCard array{member_id: int, nickname: string, avatar: string|null, code: string, expires_at: \Illuminate\Support\Carbon}
 */
class IssueClassroomCodeCards
{
    public function __construct(private readonly PolicyResolver $policy) {}

    /**
     * @param  list<int>|null  $memberIds
     * @return list<CodeCard> sorted by nickname
     */
    public function handle(FamilyMember $admin, ?array $memberIds, ?User $issuer = null): array
    {
        $family = $admin->family;
        $query = $family->members()->with('user');

        if ($memberIds === null) {
            $query->where('role', FamilyMember::ROLE_MEMBER);
        } else {
            $query->whereIn('id', $memberIds);
        }

        $members = $query->get();

        if ($memberIds !== null && $members->count() !== count(array_unique($memberIds))) {
            throw ApiException::validation('member_ids', 'Every member must be an active member of this group.');
        }

        if ($members->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($family, $members, $issuer): array {
            $now = now();
            $expiresAt = $now->copy()->addDays($this->policy->signInCodeCardTtlDays($family->tier));

            SignInCode::query()
                ->whereIn('member_id', $members->modelKeys())
                ->whereNull('used_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => $now]);

            $codes = $this->uniqueCodes($members->count());
            $rows = [];
            $cards = [];

            foreach ($members->values() as $index => $member) {
                $rows[] = [
                    'member_id' => $member->id,
                    'code_hash' => SignInCode::hashCode($codes[$index]),
                    'created_by' => $issuer?->id,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $cards[] = [
                    'member_id' => $member->id,
                    'nickname' => $member->nickname,
                    'avatar' => $member->user->avatar,
                    'code' => $codes[$index],
                    'expires_at' => $expiresAt,
                ];
            }

            SignInCode::query()->insert($rows);
            $family->forceFill(['codes_issued_at' => $now])->save();

            usort($cards, fn (array $a, array $b): int => [mb_strtolower($a['nickname']), $a['member_id']] <=> [mb_strtolower($b['nickname']), $b['member_id']]);

            return $cards;
        });
    }

    /**
     * `$count` fresh codes that collide neither with each other nor with a usable code (one lookup
     * per round; a collision is very rare, so normally one round).
     *
     * @return list<string>
     */
    private function uniqueCodes(int $count): array
    {
        $codes = [];

        while (count($codes) < $count) {
            $candidates = [];

            while (count($codes) + count($candidates) < $count) {
                $code = GenerateInviteCode::random();

                if (! in_array($code, $codes, true) && ! in_array($code, $candidates, true)) {
                    $candidates[] = $code;
                }
            }

            $hashes = array_map(fn (string $code): string => SignInCode::hashCode($code), $candidates);
            $taken = SignInCode::query()->usable()->whereIn('code_hash', $hashes)->pluck('code_hash')->flip()->all();

            foreach ($candidates as $i => $code) {
                if (! isset($taken[$hashes[$i]])) {
                    $codes[] = $code;
                }
            }
        }

        return $codes;
    }
}
