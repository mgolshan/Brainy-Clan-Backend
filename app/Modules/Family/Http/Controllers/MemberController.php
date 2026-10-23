<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Actions\UpdateAccessibility;
use App\Modules\Family\Actions\UpdateMember;
use App\Modules\Family\Http\Requests\UpdateAccessibilityRequest;
use App\Modules\Family\Http\Requests\UpdateMemberRequest;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Family\Models\FamilyMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Family')]
class MemberController
{
    use CurrentMember;

    /**
     * Update a member (admin).
     *
     * Nickname, avatar, difficulty override, birth data, role, accessibility and the member's
     * `email` login (when the band allows it). The owner cannot be demoted; only the owner may
     * change the owner's email.
     */
    #[ApiErrors('forbidden')]
    public function update(UpdateMemberRequest $request, int $member, UpdateMember $update): MemberResource
    {
        $target = $this->findInFamily($request, $member);

        return MemberResource::forViewer($update->handle($target, $request->validated(), $this->currentMember($request)), $this->currentUser($request));
    }

    /**
     * Remove a member (admin).
     *
     * Not the owner. A child account's devices are signed out.
     */
    #[ApiErrors('forbidden')]
    public function destroy(Request $request, int $member, RemoveMember $remove): Response
    {
        $remove->handle($this->findInFamily($request, $member));

        return response()->noContent();
    }

    /**
     * Update my accessibility settings.
     *
     * Text scale, reduced motion, calm timers, theme (`null` = the band's default; keys `playful`,
     * `sleek`, `modern`, `classic`, `jungle`, `space`, `neon`) and sound effects (`null` = the
     * band's `accessibility_defaults.sound_effects`). Never changes rules.
     */
    public function updateAccessibility(UpdateAccessibilityRequest $request, UpdateAccessibility $update): MemberResource
    {
        $member = $update->handle($this->currentMember($request), $request->validated());

        return MemberResource::forViewer($member, $this->currentUser($request));
    }

    private function findInFamily(Request $request, int $memberId): FamilyMember
    {
        return $this->currentMember($request)->family->members()->with(['user' => fn ($query) => $query->withExists('socialAccounts'), 'family'])->findOrFail($memberId);
    }
}
