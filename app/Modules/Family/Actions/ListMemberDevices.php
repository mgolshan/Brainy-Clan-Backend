<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\Device;
use Illuminate\Database\Eloquent\Collection;

/**
 * A member's signed-in devices, most recently seen first. Visible to family admins and to the
 * member themself.
 */
class ListMemberDevices
{
    /**
     * @return Collection<int, Device>
     */
    public function handle(FamilyMember $viewer, FamilyMember $member): Collection
    {
        self::authorize($viewer, $member);

        return Device::query()
            ->where('user_id', $member->user_id)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Admins of the member's family, or the member themself.
     */
    public static function authorize(FamilyMember $viewer, FamilyMember $member): void
    {
        $allowed = $viewer->family_id === $member->family_id && ($viewer->isAdmin() || $viewer->id === $member->id);

        if (! $allowed) {
            throw ApiException::forbidden('Only family admins can manage another member\'s devices.');
        }
    }
}
