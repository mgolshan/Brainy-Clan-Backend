<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Staff account (role `admin`) for the Filament admin: admin@brainyclan.test / password.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'admin@brainyclan.test']);
        $admin->fill(['display_name' => 'Admin', 'locale' => 'en']);

        if (! $admin->exists) {
            $admin->password = 'password';
        }

        $admin->staff_role = User::STAFF_ROLE_ADMIN;
        $admin->save();

        DemoFamilySeeder::acceptCurrentLegal($admin);
    }
}
