<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One printable sign-in code card (docs/API.md "Classroom tools" → "Code cards"). The plain code is
 * only available at issue time; the app prints it and never puts it into a URL.
 *
 * @property array{member_id: int, nickname: string, avatar: string|null, code: string, expires_at: Carbon} $resource
 */
class ClassroomCodeCardResource extends JsonResource
{
    /**
     * @return array{member_id: int, nickname: string, avatar: string|null, code: string, expires_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'member_id' => $this->resource['member_id'],
            'nickname' => $this->resource['nickname'],
            'avatar' => $this->resource['avatar'],
            'code' => $this->resource['code'],
            'expires_at' => Iso::format($this->resource['expires_at']),
        ];
    }
}
