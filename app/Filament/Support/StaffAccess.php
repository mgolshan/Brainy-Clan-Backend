<?php

namespace App\Filament\Support;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Staff roles in the admin (B11, README "Staff roles"):
 *
 * - `admin`: everything (edits, actions, AI generator, settings and age bands, audit log, Horizon).
 * - `support`: read-only — families, members, devices, subscriptions, content, webhooks, account
 *   deletion requests and the dashboard; no create/edit/delete, no actions, no AI generator, no
 *   settings/policies, no audit log, no Horizon.
 *
 * Family chat is never shown to anyone in the admin. Resources use {@see ReadOnlyForSupport} or
 * {@see AdminOnly}; custom actions and inline-editable columns check {@see self::isAdmin()}.
 */
class StaffAccess
{
    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function isStaff(): bool
    {
        return self::user()?->isStaff() ?? false;
    }

    public static function isAdmin(): bool
    {
        return self::user()?->isStaffAdmin() ?? false;
    }
}
