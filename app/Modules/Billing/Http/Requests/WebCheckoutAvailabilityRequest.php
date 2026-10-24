<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
