<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Support\PricingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebCheckoutAvailabilityRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Store country (ISO 3166-1 alpha-3) from StoreKit / Play Billing. @example USA */
            'storefront' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            /** Plan tier the app wants to link to (default `family`). @example big_clan */
            'tier' => ['nullable', 'string', Rule::in(PricingCatalog::tierIds())],
        ];
    }
}
