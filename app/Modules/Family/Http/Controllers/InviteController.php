<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\CreateInvite;
use App\Modules\Family\Actions\JoinFamilyWithInvite;
use App\Modules\Family\Actions\PreviewInvite;
use App\Modules\Family\Actions\RevokeInvite;
use App\Modules\Family\Http\Requests\CreateInviteRequest;
use App\Modules\Family\Http\Requests\JoinRequest;
use App\Modules\Family\Http\Requests\PreviewInviteRequest;
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
     * `member_limit_reached` when members + pending invites reach the tier limit. For a child
     * (band with `is_child`, see `GET /family/invites/preview`) `parental_consent: true` is required
     * (422 on `parental_consent` otherwise); it is recorded as a parental consent.
     */
    #[ApiErrors('member_limit_reached')]
    public function store(CreateInviteRequest $request, CreateInvite $create): JsonResponse
    {
        /** @var array{nickname: string, birth_year: int, birth_month: int, avatar?: string|null, parental_consent?: bool|null} $data */
        $data = [...$request->validated(), 'parental_consent' => $request->boolean('parental_consent')];

        $invite = $create->handle($this->currentMember($request), $data, $request->ip());

        return (new InviteResource($invite))->response()->setStatusCode(201);
    }

    /**
     * Preview an invitee's age band (admin).
     *
     * Lets the invite form show the parental-consent checkbox only when needed; the app never
     * computes age bands itself.
     */
    public function preview(PreviewInviteRequest $request, PreviewInvite $preview): JsonResponse
    {
        $result = $preview->handle(
            $this->currentMember($request)->family,
            $request->integer('birth_year'),
            $request->integer('birth_month'),
        );

        return response()->json(['data' => $result]);
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
     * `email`/`password` only for bands that may self-register (adults); with an email,
     * `accept_terms: true` is required (422 on `accept_terms`). Codes are
     * case-insensitive. 422 `invite_invalid`. 429 `login_locked` (`retry_after` + `Retry-After`)
     * after 5 invalid codes from this IP (5 min, then 1 h, then 24 h) or 30 failed sign-ins of
     * any kind within 1 h.
     */
    #[ApiErrors('invite_invalid', 'login_locked')]
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
            $request->boolean('accept_terms'),
            $request->ip(),
        );

        return MeResource::withToken($result['user'], $result['token'])->response()->setStatusCode(201);
    }
}
