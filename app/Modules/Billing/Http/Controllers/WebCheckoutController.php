<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\CheckWebCheckoutAvailability;
use App\Modules\Billing\Actions\CreateManageLink;
use App\Modules\Billing\Actions\SetReferralCode;
use App\Modules\Billing\Http\Requests\ReferralCodeRequest;
use App\Modules\Billing\Http\Requests\WebCheckoutAvailabilityRequest;
use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Billing')]
class WebCheckoutController
{
    use CurrentMember;

    /**
     * May the app link to web checkout? (admin)
     *
     * `storefront` is the store country from StoreKit / Play Billing (ISO 3166-1 alpha-3, e.g.
     * `USA`). `link_allowed` is true only for storefronts the server allows (none by default — store
     * rules), with web checkout configured and when the family could buy now; `url` then opens the
     * website subscribe page. When false the app shows no web prices or links.
     *
     * @response array{data: array{link_allowed: bool, url: string|null}}
     */
    public function availability(WebCheckoutAvailabilityRequest $request, CheckWebCheckoutAvailability $check): JsonResponse
    {
        $storefront = $request->validated('storefront');

        return response()->json(['data' => $check->handle($this->currentMember($request), is_string($storefront) ? $storefront : null)]);
    }

    /**
     * Link to manage the family plan (admin).
     *
     * A Paddle customer-portal link for a web plan (`store: paddle`; temporary — open it right
     * away), else the App Store / Google Play subscriptions page. 404 when the family has no
     * subscription; 503 `web_checkout_unavailable` when Paddle is not configured or unreachable.
     *
     * @response array{data: array{url: string}}
     */
    #[ApiErrors('not_found', 'web_checkout_unavailable')]
    public function manageLink(Request $request, CreateManageLink $create): JsonResponse
    {
        return response()->json(['data' => ['url' => $create->handle($this->currentMember($request)->family)]])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Set the family's referral code (admin).
     *
     * Once, before the family's first paid transaction (first touch). Case-insensitive; letters,
     * digits and dashes, up to 20. 422 `referral_code_invalid` (unknown or inactive), 409
     * `referral_code_locked` (already set, or the family already paid).
     *
     * @response array{data: array{code: string, source: 'code'}}
     */
    #[ApiErrors('referral_code_invalid', 'referral_code_locked')]
    public function referralCode(ReferralCodeRequest $request, SetReferralCode $set): JsonResponse
    {
        $attribution = $set->handle($this->currentMember($request)->family, (string) $request->string('code'));

        return response()->json(['data' => ['code' => $attribution->affiliateCode->code, 'source' => $attribution->source]]);
    }
}
