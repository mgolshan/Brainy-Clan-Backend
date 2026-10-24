<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\PaddleClient;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\WebCheckoutRequest;
use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Core\Support\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * `GET /subscribe/checkout?token=…` (the emailed link): checks the family can still buy, creates
 * the Paddle transaction — the plan's price, `custom_data { family_id, affiliate_code?,
 * affiliate_source? }` and the code's Paddle discount — and returns the Paddle checkout URL.
 *
 * A link creates at most one transaction: opening it again before it expires returns to the same
 * checkout (so a mail scanner opening it first does no harm); once the family has a plan the link
 * explains that instead.
 *
 * @phpstan-type Result array{state: 'redirect'|'invalid'|'ineligible'|'unavailable'|'error', url?: string, reason?: string, locale?: string}
 */
class OpenWebCheckout
{
    public function __construct(
        private readonly PaddleClient $paddle,
        private readonly CheckWebCheckoutEligibility $eligibility,
    ) {}

    /**
     * @return Result
     */
    public function handle(string $token): array
    {
        if (! PaddleConfig::webCheckoutAvailable()) {
            return ['state' => 'unavailable'];
        }

        $hash = $token !== '' ? WebCheckoutRequest::hashToken($token) : '';

        return DB::transaction(function () use ($hash): array {
            $request = WebCheckoutRequest::query()->with('user')->where('token_hash', $hash)->lockForUpdate()->first();

            if ($request === null || ! $request->isUsable() || $request->user === null) {
                return ['state' => 'invalid'];
            }

            $member = $request->user->activeMembership();

            if ($member === null || $member->family_id !== $request->family_id) {
                return ['state' => 'invalid', 'locale' => $request->locale];
            }

            $reason = $this->eligibility->forMember($member);

            if ($reason !== null) {
                return ['state' => 'ineligible', 'reason' => $reason, 'locale' => $request->locale];
            }

            try {
                if ($request->transaction_id === null) {
                    $transaction = $this->paddle->createTransaction($this->transactionBody($request));
                    $request->forceFill([
                        'transaction_id' => $transaction['id'],
                        'status' => WebCheckoutRequest::STATUS_OPENED,
                        'opened_at' => now(),
                    ])->save();
                }

                return ['state' => 'redirect', 'url' => $this->checkoutUrl($request), 'locale' => $request->locale];
            } catch (RuntimeException $exception) {
                Log::warning('Paddle checkout could not be created', ['family_id' => $request->family_id, 'error' => $exception->getMessage()]);

                return ['state' => 'error', 'locale' => $request->locale];
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionBody(WebCheckoutRequest $request): array
    {
        $code = $request->affiliate_code !== null ? AffiliateCode::findUsable($request->affiliate_code) : null;
        $customData = array_filter([
            'family_id' => $request->family_id,
            'affiliate_code' => $code?->code,
            'affiliate_source' => $code !== null ? $request->affiliate_source : null,
        ], fn (mixed $value): bool => $value !== null);

        return array_filter([
            'items' => [['price_id' => PaddleConfig::priceId($request->plan), 'quantity' => 1]],
            'custom_data' => $customData,
            'collection_mode' => 'automatic',
            'discount_id' => $code?->paddle_discount_id,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Paddle's hosted checkout (`PADDLE_HOSTED_CHECKOUT_URL` + the transaction) when configured,
     * else the default payment link: the website page `/subscribe/pay?_ptxn=…` (Paddle.js).
     */
    private function checkoutUrl(WebCheckoutRequest $request): string
    {
        $hosted = PaddleConfig::hostedCheckoutUrl();
        $transactionId = (string) $request->transaction_id;

        if ($hosted === '') {
            return Website::route('website.subscribe.pay', ['_ptxn' => $transactionId, ...($request->locale === 'en' ? [] : ['lang' => $request->locale])]);
        }

        $query = http_build_query(array_filter([
            'transaction_id' => $transactionId,
            'user_email' => $request->user?->email,
            'locale' => $request->locale,
        ]), '', '&', PHP_QUERY_RFC3986);

        return $hosted.(str_contains($hosted, '?') ? '&' : '?').$query;
    }
}
