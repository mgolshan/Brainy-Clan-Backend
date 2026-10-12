<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\SignInCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A freshly issued sign-in code. The plain code is only available at issue time.
 *
 * @property SignInCode $resource
 */
class SignInCodeResource extends JsonResource
{
    public function __construct(SignInCode $resource, private readonly string $code)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{code: string, member_id: int, expires_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'member_id' => $this->resource->member_id,
            'expires_at' => Iso::format($this->resource->expires_at),
        ];
    }
}
