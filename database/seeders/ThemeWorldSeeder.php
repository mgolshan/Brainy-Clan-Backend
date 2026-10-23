<?php

namespace Database\Seeders;

use App\Modules\Policy\Models\ThemeWorld;
use Illuminate\Database\Seeder;

/**
 * The default theme world catalog (docs/API.md "Worlds and unlocks"). Safe to re-run: only
 * missing keys are added, so thresholds an admin changed are kept.
 */
class ThemeWorldSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ThemeWorld::defaultRows() as $row) {
            ThemeWorld::query()->firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
