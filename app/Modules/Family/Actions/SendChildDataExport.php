<?php

namespace App\Modules\Family\Actions;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Mail\ChildDataExport;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Staff action "Start data export" (admin only, docs/OPERATIONS_HANDBOOK.md §5.5 "Privacy
 * requests"): on a parent's request sent from the family owner's own email address, mails the
 * export of a child account (a member without their own login, who cannot use `GET /me/export`)
 * to the owner's account email — never to an address given in the request. Staff never see the
 * export. The activity log gets ids only.
 */
class SendChildDataExport
{
    /**
     * @return string The owner's email address the export went to (for the staff notification)
     */
    public function handle(FamilyMember $member, User $staff): string
    {
        $member->loadMissing(['family.owner', 'user']);

        if ($member->status !== FamilyMember::STATUS_ACTIVE || $member->isOwner() || ! $member->user->isChildAccount()) {
            throw ApiException::forbidden('Only active child accounts (no own login) can be exported for a parent.');
        }

        $owner = $member->family->owner;

        if ($owner === null || $owner->email === null) {
            throw ApiException::forbidden('The family owner has no email address to send the export to.');
        }

        Mail::to($owner->email)->send(new ChildDataExport(
            $member->user,
            $member->nickname,
            in_array($owner->locale, User::LOCALES, true) ? $owner->locale : 'en',
        ));

        activity('admin')
            ->causedBy($staff)
            ->performedOn($member->family)
            ->event('child_data_exported')
            ->withProperties(['member_id' => $member->id, 'user_id' => $member->user_id, 'sent_to' => 'family_owner'])
            ->log('Child data export emailed to the family owner on a parent request');

        return $owner->email;
    }
}
