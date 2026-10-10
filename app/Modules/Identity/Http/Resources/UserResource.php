<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Only ever returned to the user themself (auth payloads, /me), so birth data is included.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name,
            'avatar' => $this->avatar,
            'email' => $this->email,
            'locale' => $this->locale,
            'birth_year' => $this->birth_year,
            'birth_month' => $this->birth_month,
        ];
    }
}
