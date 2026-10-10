<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\Invite;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invite
 */
class InviteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $band = app(PolicyResolver::class)->bandForBirth($this->birth_year, $this->birth_month, $this->family->country_code);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'nickname' => $this->nickname,
            'avatar' => $this->avatar,
            'age_band' => $band->code ?? 'adult',
            'birth_year' => $this->birth_year,
            'birth_month' => $this->birth_month,
            'expires_at' => Iso::format($this->expires_at),
            'created_at' => Iso::format($this->created_at),
        ];
    }
}
