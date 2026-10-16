<?php

namespace App\Modules\Play\Actions;

use App\Modules\Billing\Models\AdReward;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SecondChance;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Second chance after hitting the blunder cap: `free` (Family plan, limited per day) or `ad`
 * (rewarded ad, free tier, never for children). Resets mistakes and tops up items.
 *
 * Ad rewards (docs/API.md "Rewarded ads"): `reward_ref` must match an unused {@see AdReward} that
 * AdMob's server-side verification stored for this member and session; it is consumed (`used_at`).
 * No reward with that ref yet → 409 `ad_reward_pending` (callback in flight); used or foreign → 403.
 */
class GrantSecondChance
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly AddSessionItems $addItems,
    ) {}

    public function handle(PlaySession $session, string $method, ?string $rewardRef = null): PlaySession
    {
        return DB::transaction(function () use ($session, $method, $rewardRef): PlaySession {
            /** @var PlaySession $session */
            $session = PlaySession::query()->with('member.family', 'member.user')->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== PlaySession::STATUS_FAILED) {
                throw ApiException::sessionNotActive('A second chance is only possible after the blunder cap was hit.');
            }

            $rules = $this->policy->secondChanceForMember($session->member);

            $allowed = match ($method) {
                SecondChance::METHOD_FREE => $rules['free_remaining_today'] > 0,
                SecondChance::METHOD_AD => $rules['ad_allowed'],
                default => false,
            };

            if (! $allowed) {
                throw ApiException::secondChanceUnavailable(match ($method) {
                    SecondChance::METHOD_FREE => 'No free second chances left today.',
                    SecondChance::METHOD_AD => 'Ads are not available for this member.',
                    default => 'Unknown second chance method.',
                });
            }

            if ($method === SecondChance::METHOD_AD) {
                $this->consumeAdReward($session, $rewardRef);
            }

            SecondChance::query()->create([
                'play_session_id' => $session->id,
                'member_id' => $session->member_id,
                'method' => $method,
                'reward_ref' => $method === SecondChance::METHOD_AD ? $rewardRef : null,
                'granted_at' => now(),
            ]);

            $session->forceFill([
                'status' => PlaySession::STATUS_ACTIVE,
                'mistakes' => 0,
                'second_chances_used' => $session->second_chances_used + 1,
            ])->save();

            $this->addItems->topUp($session);

            return $session;
        });
    }

    private function consumeAdReward(PlaySession $session, ?string $rewardRef): void
    {
        if ($rewardRef === null || $rewardRef === '') {
            throw ApiException::validation('reward_ref', 'The reward ref is required for an ad second chance.');
        }

        $rewards = AdReward::query()->where('reward_ref', $rewardRef)->lockForUpdate()->get();

        if ($rewards->isEmpty()) {
            throw ApiException::adRewardPending();
        }

        $reward = $rewards->first(fn (AdReward $reward): bool => $reward->used_at === null
            && $reward->member_id === $session->member_id
            && $reward->play_session_id === $session->id);

        if ($reward === null) {
            throw ApiException::secondChanceUnavailable('This ad reward was already used or belongs to another member or session.');
        }

        $reward->forceFill(['used_at' => now()])->save();
    }
}
