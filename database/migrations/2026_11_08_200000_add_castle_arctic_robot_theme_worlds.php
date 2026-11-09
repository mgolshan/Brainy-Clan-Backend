<?php

use App\Modules\Policy\Models\ThemeWorld;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Three more Family-plan worlds (docs/PLAN.md "Three more Family-plan worlds", 2026-11-08):
     * `castle`, `arctic`, `robot`. Inserts only the keys that are missing, sorted after the
     * current last world (steps of 10), so rows an admin edited or reordered are never touched
     * and running it twice changes nothing. Fresh databases already get them from the
     * catalog migration. Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    private const array KEYS = ['castle', 'arctic', 'robot'];

    public function up(): void
    {
        $existing = DB::table('theme_worlds')->whereIn('key', self::KEYS)->pluck('key')->all();
        $sort = (int) DB::table('theme_worlds')->max('sort');
        $now = now();
        $rows = [];

        foreach (self::KEYS as $key) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            [$type, $required] = ThemeWorld::DEFAULT_CATALOG[$key];
            $sort += 10;
            $rows[] = ['key' => $key, 'sort' => $sort, 'unlock_type' => $type, 'required' => $required, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        }

        if ($rows !== []) {
            DB::table('theme_worlds')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        // Data only: the rows stay (an admin may have edited them).
    }
};
