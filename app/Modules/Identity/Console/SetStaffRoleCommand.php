<?php

namespace App\Modules\Identity\Console;

use App\Modules\Identity\Models\User;
use Illuminate\Console\Command;

/**
 * Grants or removes admin panel access (README "Staff roles"): `admin`, `support` (read-only) or
 * `none`. The only way to change a staff role — never through the API. Logged in the activity log.
 */
class SetStaffRoleCommand extends Command
{
    protected $signature = 'staff:role {email : The account email} {role : admin, support or none}';

    protected $description = 'Set the admin panel role of a user (admin, support or none)';

    public function handle(): int
    {
        $role = strtolower((string) $this->argument('role'));

        if ($role !== 'none' && ! in_array($role, User::STAFF_ROLES, true)) {
            $this->error('The role must be admin, support or none.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', trim((string) $this->argument('email')))->whereNull('erased_at')->first();

        if ($user === null) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        $user->staff_role = $role === 'none' ? null : $role;
        $user->save();

        activity('admin')
            ->performedOn($user)
            ->event('staff_role_changed')
            ->withProperties(['user_id' => $user->id, 'staff_role' => $user->staff_role])
            ->log('Staff role changed from the command line');

        $this->info($user->staff_role === null ? 'Admin access removed.' : "Staff role set to {$user->staff_role}.");

        return self::SUCCESS;
    }
}
