<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Family\Http\Resources\FamilyResource;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `{ user, member, family, policy }` (+ `token` for auth calls). The app calls GET /me on launch.
 *
 * @property User $resource
 */
class MeResource extends JsonResource
{
    private ?string $token = null;

    public static function withToken(User $user, string $token): self
    {
        $resource = new self($user);
        $resource->token = $token;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $user->unsetRelation('activeMember');
        $member = $user->activeMembership();
        $member?->loadMissing(['family', 'user']);

        $payload = [
            'user' => (new UserResource($user))->resolve($request),
            'member' => $member !== null ? MemberResource::forViewer($member, $user)->resolve($request) : null,
            'family' => $member !== null ? (new FamilyResource($member->family))->resolve($request) : null,
            'policy' => app(PolicyResolver::class)->forUser($user),
        ];

        return $this->token !== null ? ['token' => $this->token, ...$payload] : $payload;
    }
}
