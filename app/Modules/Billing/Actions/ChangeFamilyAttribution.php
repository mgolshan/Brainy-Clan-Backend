<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\FamilyAttribution;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff change of a family's affiliate code (Filament → Attributions), also after it is locked.
 * A reason is required; the change is written to the activity log. Existing commission entries
 * stay with the affiliate that earned them (ledger); new revenue goes to the new code.
 */
class ChangeFamilyAttribution
{
    public function handle(FamilyAttribution $attribution, AffiliateCode $code, string $reason, User $staff): FamilyAttribution
    {
        $before = [
            'affiliate_code_id' => $attribution->affiliate_code_id,
            'affiliate_id' => $attribution->affiliate_id,
        ];

        DB::transaction(function () use ($attribution, $code, $reason, $staff): void {
            $attribution->forceFill([
                'affiliate_code_id' => $code->id,
                'affiliate_id' => $code->affiliate_id,
                'changed_by_user_id' => $staff->id,
                'change_reason' => mb_substr(trim($reason), 0, 255),
            ])->save();
        });

        activity('affiliates')
            ->performedOn($attribution)
            ->causedBy($staff)
            ->event('updated')
            ->withProperties([
                'family_id' => $attribution->family_id,
                'old' => $before,
                'attributes' => ['affiliate_code_id' => $code->id, 'affiliate_id' => $code->affiliate_id, 'code' => $code->code],
                'reason' => $attribution->change_reason,
            ])
            ->log('Family attribution changed by staff');

        return $attribution;
    }
}
