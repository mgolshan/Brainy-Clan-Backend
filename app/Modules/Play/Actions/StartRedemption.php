<?php

namespace App\Modules\Play\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Support\Facades\DB;

/**
 * Redemption round: instead of an ad (and always for children), answer N easier questions
 * correctly to unlock a retry. Not available again after a failed round in the same session.
 */
class StartRedemption
{
    public function __construct(
        private readonly PolicyResolver $policy,
        private readonly AddSessionItems $addItems,
    ) {}

    public function handle(PlaySession $session): PlaySession
    {
        return DB::transaction(function () use ($session): PlaySession {
            /** @var PlaySession $session */
            $session = PlaySession::query()->with('member.family', 'member.user')->lockForUpdate()->findOrFail($session->id);

            if ($session->status !== PlaySession::STATUS_FAILED) {
                throw ApiException::sessionNotActive('A redemption round is only possible after the blunder cap was hit.');
            }

            if ($session->redemption_failed || ! $this->policy->secondChanceForMember($session->member)['redemption_allowed']) {
                throw ApiException::secondChanceUnavailable('No redemption round is available for this session.');
            }

            $count = $this->policy->playRules($session->member)['redemption_items'];
            $added = $this->addItems->handle($session, $count, redemption: true);

            if ($added->isEmpty()) {
                throw ApiException::secondChanceUnavailable('There is not enough content for a redemption round.');
            }

            $session->forceFill(['status' => PlaySession::STATUS_REDEMPTION])->save();

            return $session;
        });
    }
}
