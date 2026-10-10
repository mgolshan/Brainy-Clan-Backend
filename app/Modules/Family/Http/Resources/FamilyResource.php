<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Family\Models\Family;
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
        ];
    }
}
