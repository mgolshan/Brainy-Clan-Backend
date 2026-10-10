<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CreateFamily;
use App\Modules\Family\Actions\UpdateFamily;
use App\Modules\Family\Http\Requests\CreateFamilyRequest;
use App\Modules\Family\Http\Requests\UpdateFamilyRequest;
use App\Modules\Family\Http\Resources\FamilyResource;
use App\Modules\Family\Http\Resources\MemberResource;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController
{
    use CurrentMember;

    public function store(CreateFamilyRequest $request, CreateFamily $create): JsonResponse
    {
        /** @var array{name: string, timezone: string, country_code: string} $data */
        $data = $request->validated();

        $family = $create->handle($this->currentUser($request), $data);

        return (new FamilyResource($family))->response()->setStatusCode(201);
    }

    public function show(Request $request): JsonResponse
    {
        $member = $this->currentMember($request);
        $family = $member->family;
        $viewer = $this->currentUser($request);

        $members = $family->members()->with(['user', 'family'])->orderBy('id')->get()
            ->map(fn (FamilyMember $m) => MemberResource::forViewer($m, $viewer)->resolve($request))
            ->all();

        return new JsonResponse(['data' => [
            'family' => (new FamilyResource($family))->resolve($request),
            'members' => $members,
        ]]);
    }

    public function update(UpdateFamilyRequest $request, UpdateFamily $update): FamilyResource
    {
        /** @var array{name?: string, timezone?: string} $data */
        $data = $request->validated();

        return new FamilyResource($update->handle($this->currentMember($request)->family, $data));
    }
}
