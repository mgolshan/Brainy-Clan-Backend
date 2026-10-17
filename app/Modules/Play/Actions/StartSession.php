<?php

namespace App\Modules\Play\Actions;

use App\Modules\Content\Actions\ResolveCategoryAccess;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Starts a session in a category: target and blunder cap from the policy, and an initial batch
 * of target + cap items without answers. Abandons the member's previous unfinished sessions.
 */
class StartSession
{
    public function __construct(
        private readonly ResolveCategoryAccess $access,
        private readonly PolicyResolver $policy,
        private readonly AddSessionItems $addItems,
        private readonly AbandonSession $abandon,
    ) {}

    public function handle(FamilyMember $member, int $categoryId): PlaySession
    {
        if (! $this->access->canPlay($member, $categoryId)) {
            throw ApiException::categoryNotAllowed();
        }

        $rules = $this->policy->playRules($member);

        return DB::transaction(function () use ($member, $categoryId, $rules): PlaySession {
            // One game at a time: a new game abandons the member's older unfinished ones.
            $unfinished = PlaySession::query()
                ->where('member_id', $member->id)
                ->whereIn('status', PlaySession::UNFINISHED_STATUSES)
                ->get();

            foreach ($unfinished as $previous) {
                $this->abandon->handle($previous);
            }

            $session = PlaySession::query()->create([
                'family_id' => $member->family_id,
                'member_id' => $member->id,
                'category_id' => $categoryId,
                'target_points' => $rules['target_points'],
                'blunder_cap' => $rules['blunder_cap'],
                'mistakes' => 0,
                'points' => 0,
                'second_chances_used' => 0,
                'redemption_failed' => false,
                'status' => PlaySession::STATUS_ACTIVE,
                'started_at' => now(),
            ]);
            $session->setRelation('member', $member);

            $added = $this->addItems->handle($session, $rules['target_points'] + $rules['blunder_cap']);

            if ($added->isEmpty()) {
                throw ApiException::validation('category_id', 'There is no playable content in this category yet.');
            }

            return $session;
        });
    }
}
