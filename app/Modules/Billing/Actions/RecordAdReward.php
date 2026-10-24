<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\AdReward;
use App\Modules\Billing\Support\AdMobSignatureVerifier;
use App\Modules\Billing\Support\WebhookRejections;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\PlaySession;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Stores a rewarded ad from AdMob's server-side verification callback (docs/API.md "Rewarded
 * ads"). The signature is checked first (400 `ssv_signature_invalid`). `custom_data` is
 * `<reward_ref>:<session_id>` and `user_id` the member id. Idempotent by `transaction_id`.
 * Callbacks without usable custom data (e.g. AdMob's "verify URL" test) or for an unknown
 * member/session are acknowledged but not stored, so AdMob does not retry them. Bad signatures and
 * signed callbacks with unusable custom data are counted in `webhook_rejections` (admin dashboard).
 */
class RecordAdReward
{
    public const string OUTCOME_STORED = 'stored';

    public const string OUTCOME_DUPLICATE = 'duplicate';

    public const string OUTCOME_IGNORED = 'ignored';

    public function __construct(
        private readonly AdMobSignatureVerifier $verifier,
        private readonly WebhookRejections $rejections,
    ) {}

    /**
     * @return array{transaction_id: string|null, outcome: string}
     */
    public function handle(string $rawQuery): array
    {
        if (! $this->verifier->verify($rawQuery)) {
            $this->rejections->record(WebhookRejections::PROVIDER_ADMOB, WebhookRejections::REASON_SIGNATURE_INVALID);

            throw ApiException::ssvSignatureInvalid();
        }

        parse_str($rawQuery, $params);
        $transactionId = is_string($params['transaction_id'] ?? null) && $params['transaction_id'] !== '' ? $params['transaction_id'] : null;

        if ($transactionId === null) {
            return ['transaction_id' => null, 'outcome' => self::OUTCOME_IGNORED];
        }

        if (AdReward::query()->where('transaction_id', $transactionId)->exists()) {
            return ['transaction_id' => $transactionId, 'outcome' => self::OUTCOME_DUPLICATE];
        }

        $customData = is_string($params['custom_data'] ?? null) ? $params['custom_data'] : '';
        $memberId = is_string($params['user_id'] ?? null) && ctype_digit($params['user_id']) ? (int) $params['user_id'] : null;

        if (preg_match('/^([A-Za-z0-9-]{1,100}):(\d+)$/', $customData, $matches) !== 1 || $memberId === null
            || ! FamilyMember::query()->whereKey($memberId)->exists()
            || ! PlaySession::query()->whereKey((int) $matches[2])->exists()) {
            // Without custom data it is AdMob's "Verify URL" test or an app build without SSV options: not counted.
            if ($customData !== '') {
                $this->rejections->record(WebhookRejections::PROVIDER_ADMOB, WebhookRejections::REASON_INVALID_PAYLOAD);
            }

            return ['transaction_id' => $transactionId, 'outcome' => self::OUTCOME_IGNORED];
        }

        try {
            AdReward::query()->create([
                'transaction_id' => $transactionId,
                'reward_ref' => $matches[1],
                'member_id' => $memberId,
                'play_session_id' => (int) $matches[2],
                'ad_unit' => is_string($params['ad_unit'] ?? null) ? $params['ad_unit'] : null,
                'reward_item' => is_string($params['reward_item'] ?? null) ? $params['reward_item'] : null,
                'reward_amount' => is_numeric($params['reward_amount'] ?? null) ? (int) $params['reward_amount'] : null,
                'payload' => $params,
            ]);
        } catch (UniqueConstraintViolationException) {
            return ['transaction_id' => $transactionId, 'outcome' => self::OUTCOME_DUPLICATE];
        }

        return ['transaction_id' => $transactionId, 'outcome' => self::OUTCOME_STORED];
    }
}
