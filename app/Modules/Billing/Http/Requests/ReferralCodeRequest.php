<?php

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Core\Exceptions\ApiException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /family/referral-code` — a malformed code is `referral_code_invalid` like an unknown one.
 */
class ReferralCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** Case-insensitive: letters, digits and dashes, up to 20. @example MAYA */
            'code' => ['required', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! AffiliateCode::isWellFormed($this->input('code'))) {
                    throw ApiException::referralCodeInvalid();
                }
            },
        ];
    }
}
