<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CreateInvite;
use App\Modules\Family\Actions\JoinFamilyWithInvite;
use App\Modules\Family\Actions\RevokeInvite;
use App\Modules\Family\Http\Requests\CreateInviteRequest;
use App\Modules\Family\Http\Requests\JoinRequest;
use App\Modules\Family\Http\Resources\InviteResource;
use App\Modules\Identity\Http\Resources\MeResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

#[Group('Family')]
class InviteController
{
    use CurrentMember;

    /**
     * Pending invites (admin).
     *
     * Unused and unexpired invites.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $invites = $this->currentMember($request)->family->pendingInvites()->with('family')->latest('id')->get();

        return InviteResource::collection($invites);
    }

    /**
     * Create an invite (admin).
     *
     * Nickname + birth data of the invitee; 8-character code valid 7 days. 403
     * `member_limit_reached` when members + pending invites reach the tier limit.
     */
    #[ApiErrors('member_limit_reached')]
    public function store(CreateInviteRequest $request, CreateInvite $create): JsonResponse
    {
        /** @var array{nickname: string, birth_year: int, birth_month: int, avatar?: string|null} $data */
        $data = $request->validated();

        $invite = $create->handle($this->currentMember($request), $data);

        return (new InviteResource($invite))->response()->setStatusCode(201);
    }

    /**
     * Revoke an invite (admin).
     */
    public function destroy(Request $request, int $invite, RevokeInvite $revoke): Response
    {
        $revoke->handle($this->currentMember($request)->family->pendingInvites()->findOrFail($invite));

        return response()->noContent();
    }

    /**
     * Join a family with an invite code.
     *
     * Creates the user from the invite and the membership, and a device-bound token.
     * `email`/`password` only for bands that may self-register (adults). Codes are
     * case-insensitive. 422 `invite_invalid`.
     */
    #[ApiErrors('invite_invalid')]
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
