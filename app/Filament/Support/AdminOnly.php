<?php

namespace App\Filament\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * For Filament resources only the `admin` role may see at all (settings, age bands, audit log):
 * hidden from the navigation and 403 for `support`.
 */
trait AdminOnly
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return StaffAccess::isAdmin() ? parent::getAuthorizationResponse($action, $record) : Response::deny();
    }
}
