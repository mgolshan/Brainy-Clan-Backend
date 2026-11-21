<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Support\PaddleConfig;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Core\Support\Website;
use App\Modules\Family\Models\Family;
use App\Modules\Family\Models\FamilyMember;

/**
 * `GET /family/web-checkout/availability?storefront=USA&tier=big_clan`: whether the app may link to
 * web checkout for a plan tier. Only for storefronts in `billing.web_checkout.link_storefronts`
 * (empty by default — store rules), with Paddle configured for that tier, and when this admin's
 * family could buy now. A Classroom whose family owner is younger than `classroom.owner_min_age`
 * (18) is refused outright: 403 `owner_too_young` with `min_age`.
 */
class CheckWebCheckoutAvailability
{
    public function __construct(private readonly CheckWebCheckoutEligibility $eligibility) {}

    /**
     * @return array{link_allowed: bool, url: string|null}
     */
    public function handle(FamilyMember $member, ?string $storefront, string $tier = Family::TIER_FAMILY): array
    {
        $minAge = $this->eligibility->ownerTooYoungFor($member, $tier);

        if ($minAge !== null) {
            throw ApiException::ownerTooYoung($minAge, "A Classroom needs a family owner who is at least {$minAge}.");
        }

        $storefront = $storefront !== null ? strtoupper($storefront) : null;
        $allowed = $storefront !== null
            && in_array($storefront, (array) config('billing.web_checkout.link_storefronts', []), true)
            && PaddleConfig::webCheckoutAvailable($tier)
            && $this->eligibility->forMember($member, $tier) === null;

        return [
            'link_allowed' => $allowed,
            'url' => $allowed ? Website::route('website.subscribe', ['tier' => $tier, 'plan' => 'annual', 'src' => 'app']) : null,
        ];
    }
}
