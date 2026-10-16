<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Family\Http\Resources\FamilyResource;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Identity\Actions\GetLegalStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `{ user, member, family, policy, legal }` (+ `token` for auth calls). The app calls GET /me on launch.
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

        $policy = app(PolicyResolver::class)->forUser($user);

        $payload = [
            'user' => (new UserResource($user))->resolve($request),
            'member' => $member !== null ? MemberResource::forViewer($member, $user)->resolve($request) : null,
            'family' => $member !== null ? (new FamilyResource($member->family))->resolve($request) : null,
            'policy' => $policy,
            /**
             * Legal status: `needs_acceptance` is true for non-child users whose accepted terms/privacy
             * versions differ from the current ones (then `POST /me/legal-acceptance`); always false for children.
             *
             * @var array{needs_acceptance: bool, terms_version: string, privacy_version: string, accepted_terms_version: string|null, accepted_privacy_version: string|null}
             */
            'legal' => app(GetLegalStatus::class)->handle($user, (bool) $policy['is_child']),
        ];

        return $this->token !== null ? ['token' => $this->token, ...$payload] : $payload;
    }
}
