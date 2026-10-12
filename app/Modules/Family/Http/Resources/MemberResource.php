<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Birth data and the email login (`email`, `has_password`) are only included for the member
 * themself and for family admins.
 *
 * @property FamilyMember $resource
 */
class MemberResource extends JsonResource
{
    private ?User $viewer = null;

    public static function forViewer(FamilyMember $member, ?User $viewer): self
    {
        $resource = new self($member);
        $resource->viewer = $viewer;

        return $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $member = $this->resource;
        $policy = app(PolicyResolver::class);
        $band = $policy->bandForMember($member);

        $viewer = $this->viewer ?? $request->user();
        $showPrivate = $viewer instanceof User && $this->canSeePrivateData($member, $viewer);

        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'nickname' => $member->nickname,
            'avatar' => $member->user->avatar,
            'role' => $member->role,
            'age_band' => $band->code ?? 'adult',
            'is_child' => $policy->isChildBand($band),
            'birth_year' => $this->when($showPrivate, $member->user->birth_year),
            'birth_month' => $this->when($showPrivate, $member->user->birth_month),
            'difficulty_override' => $member->difficulty_override,
            'accessibility' => Accessibility::forMember($member, $band),
            'default_ui_theme' => (string) ($band?->rule('ui_theme') ?? 'modern'),
            'status' => $member->status,
            'joined_at' => Iso::format($member->joined_at ?? $member->created_at),
            'email' => $this->when($showPrivate, $member->user->email),
            'has_password' => $this->when($showPrivate, $member->user->password !== null),
            // From the band already resolved above (cached policy snapshot): no extra query per member.
            'email_login_allowed' => $this->when($showPrivate, $policy->emailLoginAllowed($band)),
        ];
    }

    private function canSeePrivateData(FamilyMember $member, User $viewer): bool
    {
        if ($viewer->id === $member->user_id) {
            return true;
        }

        $viewerMember = $viewer->activeMembership();

        return $viewerMember !== null && $viewerMember->family_id === $member->family_id && $viewerMember->isAdmin();
    }
}
