<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Identity\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A linked Google / Facebook account.
 *
 * @property SocialAccount $resource
 */
class SocialAccountResource extends JsonResource
{
    /**
     * @return array{provider: 'google'|'facebook', email: string|null, linked_at: string|null}
     */
    public function toArray(Request $request): array
    {
        /** @var 'google'|'facebook' $provider */
        $provider = $this->resource->provider;

        return [
            'provider' => $provider,
            'email' => $this->resource->email,
            'linked_at' => Iso::format($this->resource->linked_at),
        ];
    }
}
