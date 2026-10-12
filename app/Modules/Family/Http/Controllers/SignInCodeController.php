<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\IssueSignInCode;
use App\Modules\Family\Actions\SignInWithCode;
use App\Modules\Family\Http\Requests\SignInWithCodeRequest;
use App\Modules\Family\Http\Resources\SignInCodeResource;
use App\Modules\Identity\Http\Resources\MeResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member sign-in without email: an admin issues a one-time code, the member types it on the
 * new device.
 */
#[Group('Family')]
class SignInCodeController
{
    use CurrentMember;

    /**
     * Issue a sign-in code for a member.
     *
     * One-time code for that member's new device: valid 15 minutes, single use. Issuing a new code
     * voids the member's older unused codes. Family admins only.
     */
    #[ApiErrors('forbidden')]
    public function store(Request $request, int $member, IssueSignInCode $issue): JsonResponse
    {
        $target = $this->currentMember($request)->family->members()->with('user')->findOrFail($member);
        $issued = $issue->handle($target, $this->currentUser($request));

        return (new SignInCodeResource($issued['sign_in_code'], $issued['code']))->response()->setStatusCode(201);
    }

    /**
     * Sign in with a sign-in code.
     *
     * Redeems an admin-issued code on this device and returns a device-bound token, like
     * `/auth/login`. 422 `sign_in_code_invalid` when the code is unknown, used or expired.
     *
     * @unauthenticated
     */
    #[ApiErrors('sign_in_code_invalid')]
    public function redeem(SignInWithCodeRequest $request, SignInWithCode $signIn): MeResource
    {
        $result = $signIn->handle(
            (string) $request->string('code'),
            (string) $request->string('device_name'),
            (string) $request->string('platform'),
            $request->appVersion(),
        );

        return MeResource::withToken($result['user'], $result['token']);
    }
}
