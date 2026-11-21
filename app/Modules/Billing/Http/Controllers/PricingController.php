<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Support\PricingCatalog;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Billing')]
class PricingController
{
    /**
     * Paid plan prices per tier (website).
     *
     * Public. Display prices from server config, mirroring the store and Paddle prices; the app shows
     * the store prices from the RevenueCat offering instead. `?currency=CAD` for Canadian dollars
     * (default `USD`). One entry per paid tier (`family`, `big_clan`, `classroom`, in rank order):
     * `members` is the tier's `limits.max_members`, `min_owner_age` the family owner's minimum age
     * for that tier (`classroom`: `classroom.owner_min_age`, 18; null for the others), `available` is false when web checkout isn't
     * configured for that tier. `per_month` and `saving_percent` (against the monthly plan) are computed.
     *
     * @unauthenticated
     *
     * @response array{data: array{currency: string, tiers: list<array{id: 'family'|'big_clan'|'classroom', members: int, min_owner_age: int|null, highlighted: bool, available: bool, plans: list<array{id: 'monthly'|'semiannual'|'annual', price: string, per_month: string, saving_percent: int, trial_days: int, highlighted: bool}>}>}}
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => ['nullable', 'string', Rule::in([...PricingCatalog::currencies(), ...array_map('strtolower', PricingCatalog::currencies())])],
        ]);

        $currency = is_string($validated['currency'] ?? null) ? $validated['currency'] : null;

        return response()->json(['data' => PricingCatalog::forCurrency($currency)])
            ->header('Cache-Control', 'public, max-age=300');
    }
}
