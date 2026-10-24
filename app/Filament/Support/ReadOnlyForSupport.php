<?php

namespace App\Filament\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * For Filament resources: admins keep full access; the `support` role may only `viewAny` and
 * `view` (create, update, delete, restore, replicate, reorder... are denied, which hides the
 * built-in actions and answers 403 on create/edit pages). Custom actions and inline-editable
 * columns must check {@see StaffAccess::isAdmin()} themselves.
 */
trait ReadOnlyForSupport
{
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if (StaffAccess::isAdmin()) {
            return parent::getAuthorizationResponse($action, $record);
        }

        $ability = $action instanceof UnitEnum ? $action->name : $action;

        if (StaffAccess::isStaff() && in_array($ability, ['viewAny', 'view'], true)) {
            return parent::getAuthorizationResponse($action, $record);
        }

        return Response::deny();
    }
}
