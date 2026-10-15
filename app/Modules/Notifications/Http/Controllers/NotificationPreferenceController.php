<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Notifications\Actions\GetNotificationPreferences;
use App\Modules\Notifications\Actions\UpdateNotificationPreferences;
use App\Modules\Notifications\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Modules\Notifications\Http\Resources\NotificationPreferencesResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

/**
 * Notification preferences. Defaults apply until changed: children (by age band) quiet hours
 * 19:30-07:30, others 21:30-07:00; daily reminder off, at 17:00.
 */
#[Group('Notifications')]
class NotificationPreferenceController
{
    use CurrentMember;

    /**
     * My notification preferences.
     */
    public function show(Request $request, GetNotificationPreferences $get): NotificationPreferencesResource
    {
        return new NotificationPreferencesResource($get->handle($this->currentMember($request)));
    }

    /**
     * Update my notification preferences.
     *
     * All fields optional. `quiet_from` and `quiet_to` are set or cleared (null) together.
     */
    public function update(UpdateNotificationPreferencesRequest $request, UpdateNotificationPreferences $update): NotificationPreferencesResource
    {
        $member = $this->currentMember($request);

        return new NotificationPreferencesResource($update->handle($member, $member, $this->payload($request)));
    }

    /**
     * A member's notification preferences (admin).
     */
    #[ApiErrors('forbidden')]
    public function showMember(Request $request, int $member, GetNotificationPreferences $get): NotificationPreferencesResource
    {
        $target = $this->findInFamily($request, $member);
        UpdateNotificationPreferences::authorize($this->currentMember($request), $target);

        return new NotificationPreferencesResource($get->handle($target));
    }

    /**
     * Update a member's notification preferences (admin), e.g. a child's quiet hours.
     */
    #[ApiErrors('forbidden')]
    public function updateMember(UpdateNotificationPreferencesRequest $request, int $member, UpdateNotificationPreferences $update): NotificationPreferencesResource
    {
        return new NotificationPreferencesResource($update->handle($this->currentMember($request), $this->findInFamily($request, $member), $this->payload($request)));
    }

    /**
     * @return array{types?: array<string, bool>, quiet_from?: string|null, quiet_to?: string|null, reminder_time?: string}
     */
    private function payload(UpdateNotificationPreferencesRequest $request): array
    {
        /** @var array{types?: array<string, bool>, quiet_from?: string|null, quiet_to?: string|null, reminder_time?: string} $data */
        $data = $request->validated();

        return $data;
    }

    private function findInFamily(Request $request, int $memberId): FamilyMember
    {
        /** @var FamilyMember $member */
        $member = $this->currentMember($request)->family->members()->findOrFail($memberId);

        return $member;
    }
}
