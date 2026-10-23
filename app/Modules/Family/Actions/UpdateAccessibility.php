<?php

namespace App\Modules\Family\Actions;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Family\Support\Accessibility;
use App\Modules\Policy\Actions\EvaluateWorldUnlocks;

/**
 * A member changes their own accessibility settings (text size, motion, timers, theme, sound effects).
 * A theme world must be unlocked for the member (403 `world_locked`); `theme: null` always works.
 */
class UpdateAccessibility
{
    public function __construct(private readonly EvaluateWorldUnlocks $worlds) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(FamilyMember $member, array $data): FamilyMember
    {
        if (array_key_exists('theme', $data)) {
            $this->worlds->ensureCanPick($member, $data['theme']);
        }

        $member->forceFill(['accessibility' => Accessibility::merge($member->accessibility, $data)])->save();

        return $member;
    }
}
