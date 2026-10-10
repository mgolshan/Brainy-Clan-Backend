<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate:fresh --seed` builds a complete local dataset. Every seeder is
 * safe to re-run (`php artisan db:seed`).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PolicySeeder::class,
            AdminUserSeeder::class,
            CategorySeeder::class,
            ChallengeTypeSeeder::class,
            ContentSeeder::class,
            DemoFamilySeeder::class,
        ]);
    }
}
