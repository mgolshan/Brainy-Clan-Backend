<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\PaddleClient;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * `POST /family/subscription/manage-link`: where an admin manages the family's plan. A web plan
 * (Paddle) gets a fresh customer-portal session link (cancel, card, invoices; temporary, never
 * stored); a store plan gets the store's subscription page. 404 when the family has nothing to
 * manage; 503 `web_checkout_unavailable` when Paddle is not configured or can't be reached.
 */
class CreateManageLink
{
    public function __construct(private readonly PaddleClient $paddle) {}

    public function handle(Family $family): string
    {
        $subscription = Subscription::currentFor($family->id);

        if ($subscription === null) {
            throw ApiException::notFound('Your family has no subscription to manage.');
        }

        if (! $subscription->isPaddle()) {
            $url = config('billing.store_manage_urls.'.$subscription->store);

            return is_string($url) && $url !== ''
                ? $url
                : throw ApiException::notFound('This subscription is not managed in a store we can link to.');
        }

        if (! $this->paddle->isConfigured() || $subscription->customer_id === null) {
            throw ApiException::webCheckoutUnavailable();
        }

        try {
            return $this->paddle->customerPortalUrl($subscription->customer_id, array_values(array_filter([$subscription->external_id])));
        } catch (RuntimeException $exception) {
            Log::warning('Paddle portal session failed', ['family_id' => $family->id, 'error' => $exception->getMessage()]);

            throw ApiException::webCheckoutUnavailable('The subscription page can\'t be opened right now. Please try again.');
        }
    }
}
