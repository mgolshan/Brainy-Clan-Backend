<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Core\Support\Website;
use App\Modules\Family\Models\FamilyMember;

/**
 * `GET /family/web-checkout/availability?storefront=USA`: whether the app may link to web
 * checkout. Only for storefronts in `billing.web_checkout.link_storefronts` (empty by default —
 * store rules), with Paddle configured, and when this admin's family could buy now.
 */
class CheckWebCheckoutAvailability
{
    public function __construct(private readonly CheckWebCheckoutEligibility $eligibility) {}

    /**
     * @return array{link_allowed: bool, url: string|null}
     */
    public function handle(FamilyMember $member, ?string $storefront): array
    {
        $storefront = $storefront !== null ? strtoupper($storefront) : null;
        $allowed = $storefront !== null
            && in_array($storefront, (array) config('billing.web_checkout.link_storefronts', []), true)
            && PaddleConfig::webCheckoutAvailable()
            && $this->eligibility->forMember($member) === null;

        return [
            'link_allowed' => $allowed,
            'url' => $allowed ? Website::route('website.subscribe', ['plan' => 'annual', 'src' => 'app']) : null,
        ];
    }
}
