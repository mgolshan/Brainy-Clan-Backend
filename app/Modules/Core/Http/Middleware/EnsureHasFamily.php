<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `family` middleware: 409 `no_family` unless the user has an active family membership.
 * `family:admin` additionally requires the `admin` role (403 `forbidden`).
 */
class EnsureHasFamily
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $member = $user->activeMembership();

        if ($member === null) {
            throw ApiException::noFamily();
        }

        if ($role === 'admin' && ! $member->isAdmin()) {
            throw ApiException::forbidden('Only family admins can do this.');
        }

        return $next($request);
    }
}
