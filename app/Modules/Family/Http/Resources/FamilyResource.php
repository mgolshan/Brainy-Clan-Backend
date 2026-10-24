<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Family\Models\Family;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Family
 */
class FamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'timezone' => $this->timezone,
            'country_code' => $this->country_code,
            'tier' => $this->tier,
            'owner_user_id' => $this->owner_user_id,
            'member_count' => $this->members()->count(),
            // The affiliate code the family came with (B10): the real value only for its admins.
            'referral_code' => $this->referralCodeFor($request),
        ];
    }

    private function referralCodeFor(Request $request): ?string
    {
        $viewer = $request->user();
        $membership = $viewer instanceof User ? $viewer->activeMembership() : null;

        if ($membership === null || $membership->family_id !== $this->id || ! $membership->isAdmin()) {
            return null;
        }

        /** @var Family $family */
        $family = $this->resource;
        $family->loadMissing('attribution.affiliateCode');

        return $family->attribution?->affiliateCode->code;
    }
}
