<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\RevokeDevice;
use App\Modules\Identity\Models\Device;

/**
 * Signs one of a member's devices out (e.g. a lost tablet): revokes its token and deletes the
 * device row. Admins of the family or the member themself; 404 for another user's device.
 */
class SignOutMemberDevice
{
    public function __construct(private readonly RevokeDevice $revoke) {}

    public function handle(FamilyMember $viewer, FamilyMember $member, int $deviceId): void
    {
        ListMemberDevices::authorize($viewer, $member);

        /** @var Device $device */
        $device = Device::query()->where('user_id', $member->user_id)->findOrFail($deviceId);

        $this->revoke->handle($device);
    }
}
