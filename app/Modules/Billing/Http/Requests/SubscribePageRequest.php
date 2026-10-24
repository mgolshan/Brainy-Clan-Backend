<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Support\PricingCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The website subscribe form (`POST /web/subscribe`). `website` is a honeypot hidden from people.
 * A referral code must be an active code (codes are public, so this reveals nothing about emails).
 */
class SubscribePageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fr = $this->query('lang') === 'fr';

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'plan' => ['required', 'string', Rule::in(PricingCatalog::planIds())],
            'code' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) use ($fr): void {
                if (is_string($value) && trim($value) !== '' && AffiliateCode::findUsable($value) === null) {
                    $fail($fr ? 'Ce code de parrainage est inconnu ou n’est plus actif.' : 'This referral code is unknown or no longer active.');
                }
            }],
            'src' => ['nullable', 'string', 'max:10'],
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isBot(): bool
    {
        return $this->filled('website');
    }

    public function referralCode(): ?string
    {
        $code = $this->input('code');

        return is_string($code) && trim($code) !== '' ? AffiliateCode::normalize($code) : null;
    }
}
