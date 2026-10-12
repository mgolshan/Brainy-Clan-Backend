<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Actions\ListMemberDevices;
use App\Modules\Family\Actions\SignOutMemberDevice;
use App\Modules\Family\Http\Resources\DeviceResource;
use App\Modules\Family\Models\FamilyMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * A member's signed-in devices (family admins, or the member themself).
 */
#[Group('Family')]
class MemberDeviceController
{
    use CurrentMember;

    /**
     * List a member's devices.
     */
    #[ApiErrors('forbidden')]
    public function index(Request $request, int $member, ListMemberDevices $list): AnonymousResourceCollection
    {
        return DeviceResource::collection($list->handle($this->currentMember($request), $this->findInFamily($request, $member)));
    }

    /**
     * Sign a device out.
     *
     * Revokes the device's token (e.g. a lost tablet). Signing out the current device works like logout.
     */
    #[ApiErrors('forbidden')]
    public function destroy(Request $request, int $member, int $device, SignOutMemberDevice $signOut): Response
    {
        $signOut->handle($this->currentMember($request), $this->findInFamily($request, $member), $device);

        return response()->noContent();
    }

    private function findInFamily(Request $request, int $memberId): FamilyMember
    {
        /** @var FamilyMember $member */
        $member = $this->currentMember($request)->family->members()->findOrFail($memberId);

        return $member;
    }
}
