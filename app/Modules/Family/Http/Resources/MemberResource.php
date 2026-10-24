<?php

namespace App\Modules\Family\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\Actions\EvaluateWorldUnlocks;
use App\Modules\Policy\PolicyResolver;
use App\Modules\Policy\Support\WorldProgress;
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

    private ?WorldProgress $worldProgress = null;

    /**
     * @param  WorldProgress|null  $worldProgress  from EvaluateWorldUnlocks::progressForMembers() in lists, so
     *                                             `theme_unlocked` needs no query per member
     */
    public static function forViewer(FamilyMember $member, ?User $viewer, ?WorldProgress $worldProgress = null): self
    {
        $resource = new self($member);
        $resource->viewer = $viewer;
        $resource->worldProgress = $worldProgress;

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
            // False when the stored theme world is locked again (e.g. the family plan ended); the theme is kept.
            'theme_unlocked' => app(EvaluateWorldUnlocks::class)->themeUnlocked($member, $this->worldProgress),
            'chat_enabled' => (bool) $member->chat_enabled,
            'status' => $member->status,
            'joined_at' => Iso::format($member->joined_at ?? $member->created_at),
            'email' => $this->when($showPrivate, $member->user->email),
            'has_password' => $this->when($showPrivate, $member->user->password !== null),
            // From the band already resolved above (cached policy snapshot): no extra query per member.
            'email_login_allowed' => $this->when($showPrivate, $policy->emailLoginAllowed($band)),
            // Lists load `user` with `withExists('socialAccounts')`: no query per member.
            'has_own_login' => $this->when($showPrivate, fn (): bool => $member->user->hasOwnLogin()),
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
