<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CreateInvite;
use App\Modules\Family\Actions\JoinFamilyWithInvite;
use App\Modules\Family\Actions\RevokeInvite;
use App\Modules\Family\Http\Requests\CreateInviteRequest;
use App\Modules\Family\Http\Requests\JoinRequest;
use App\Modules\Family\Http\Resources\InviteResource;
use App\Modules\Identity\Http\Resources\MeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InviteController
{
    use CurrentMember;

    public function index(Request $request): AnonymousResourceCollection
    {
        $invites = $this->currentMember($request)->family->pendingInvites()->with('family')->latest('id')->get();

        return InviteResource::collection($invites);
    }

    public function store(CreateInviteRequest $request, CreateInvite $create): JsonResponse
    {
        /** @var array{nickname: string, birth_year: int, birth_month: int, avatar?: string|null} $data */
        $data = $request->validated();

        $invite = $create->handle($this->currentMember($request), $data);

        return (new InviteResource($invite))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, int $invite, RevokeInvite $revoke): Response
    {
        $revoke->handle($this->currentMember($request)->family->pendingInvites()->findOrFail($invite));

        return response()->noContent();
    }

    public function join(JoinRequest $request, JoinFamilyWithInvite $join): JsonResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $result = $join->handle(
            (string) $request->string('code'),
            (string) $request->string('device_name'),
            (string) $request->string('platform'),
            is_string($email) && $email !== '' ? $email : null,
            is_string($password) && $password !== '' ? $password : null,
            $request->appVersion(),
        );

        return MeResource::withToken($result['user'], $result['token'])->response()->setStatusCode(201);
    }
}
