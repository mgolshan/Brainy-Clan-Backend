<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\Family;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * `POST /family/referral-code`: a family admin enters an affiliate code once, before the
 * family's first paid transaction (first touch). 409 `referral_code_locked` when the family has
 * a code already or has paid; 422 `referral_code_invalid` for an unknown or inactive code.
 */
class SetReferralCode
{
    public function handle(Family $family, string $code): FamilyAttribution
    {
        try {
            return DB::transaction(function () use ($family, $code): FamilyAttribution {
                /** @var Family $locked */
                $locked = Family::query()->lockForUpdate()->findOrFail($family->id);

                if ($locked->first_paid_at !== null || FamilyAttribution::query()->where('family_id', $locked->id)->exists()) {
                    throw ApiException::referralCodeLocked();
                }

                $affiliateCode = AffiliateCode::findUsable($code) ?? throw ApiException::referralCodeInvalid();

                return FamilyAttribution::query()->create([
                    'family_id' => $locked->id,
                    'affiliate_code_id' => $affiliateCode->id,
                    'affiliate_id' => $affiliateCode->affiliate_id,
                    'source' => FamilyAttribution::SOURCE_CODE,
                    'attributed_at' => now(),
                ])->setRelation('affiliateCode', $affiliateCode);
            });
        } catch (UniqueConstraintViolationException) {
            throw ApiException::referralCodeLocked();
        }
    }
}
