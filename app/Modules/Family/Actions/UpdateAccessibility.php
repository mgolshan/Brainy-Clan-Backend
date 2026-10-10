<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;

/**
 * A member changes their own accessibility settings (text size, motion, timers, theme).
 */
class UpdateAccessibility
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(FamilyMember $member, array $data): FamilyMember
    {
        $member->forceFill(['accessibility' => Accessibility::merge($member->accessibility, $data)])->save();

        return $member;
    }
}
