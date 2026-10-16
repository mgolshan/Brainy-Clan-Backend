<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CreateFamily;
use App\Modules\Family\Actions\UpdateFamily;
use App\Modules\Family\Http\Requests\CreateFamilyRequest;
use App\Modules\Family\Http\Requests\UpdateFamilyRequest;
use App\Modules\Family\Http\Resources\FamilyResource;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Family\Models\FamilyMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Family', 'The family, its members and invites, member sign-in codes and devices.', 2)]
class FamilyController
{
    use CurrentMember;

    /**
     * Create a family.
     *
     * The caller becomes the owner and an admin; tier `free`. 409 `already_in_family`.
     */
    #[ApiErrors('already_in_family')]
    public function store(CreateFamilyRequest $request, CreateFamily $create): JsonResponse
    {
        /** @var array{name: string, timezone: string, country_code: string} $data */
        $data = $request->validated();

        $family = $create->handle($this->currentUser($request), $data);

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }

    /**
     * My family and its members.
     */
    public function show(Request $request): JsonResponse
    {
        $member = $this->currentMember($request);
        $family = $member->family;
        $viewer = $this->currentUser($request);

        $members = $family->members()->with(['user' => fn ($query) => $query->withExists('socialAccounts'), 'family'])->orderBy('id')->get()
            ->map(fn (FamilyMember $m) => MemberResource::forViewer($m, $viewer)->resolve($request))
            ->all();

        return new JsonResponse(['data' => [
            'family' => (new FamilyResource($family))->resolve($request),
            'members' => $members,
        ]]);
    }

    /**
     * Update the family (admin).
     *
     * Name and/or timezone.
     */
    public function update(UpdateFamilyRequest $request, UpdateFamily $update): FamilyResource
    {
        /** @var array{name?: string, timezone?: string} $data */
        $data = $request->validated();

        return new FamilyResource($update->handle($this->currentMember($request)->family, $data));
    }
}
