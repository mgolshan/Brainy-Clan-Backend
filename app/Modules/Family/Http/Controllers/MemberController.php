<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\RemoveMember;
use App\Modules\Family\Actions\UpdateAccessibility;
use App\Modules\Family\Actions\UpdateMember;
use App\Modules\Family\Http\Requests\UpdateAccessibilityRequest;
use App\Modules\Family\Http\Requests\UpdateMemberRequest;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MemberController
{
    use CurrentMember;

    public function update(UpdateMemberRequest $request, int $member, UpdateMember $update): MemberResource
    {
        $target = $this->findInFamily($request, $member);

        return MemberResource::forViewer($update->handle($target, $request->validated()), $this->currentUser($request));
    }

    public function destroy(Request $request, int $member, RemoveMember $remove): Response
    {
        $remove->handle($this->findInFamily($request, $member));

        return response()->noContent();
    }

    public function updateAccessibility(UpdateAccessibilityRequest $request, UpdateAccessibility $update): MemberResource
    {
        $member = $update->handle($this->currentMember($request), $request->validated());

        return MemberResource::forViewer($member, $this->currentUser($request));
    }

    private function findInFamily(Request $request, int $memberId): FamilyMember
    {
        return $this->currentMember($request)->family->members()->with(['user', 'family'])->findOrFail($memberId);
    }
}
