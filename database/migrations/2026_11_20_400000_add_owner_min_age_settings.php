<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (docs/API.md "Plans and tiers" → "Owner ages"): the global settings
     * `family.owner_min_age` = 16 (create a family, self sign-up) and `classroom.owner_min_age` = 18
     * (a Classroom's family owner). Only missing keys are inserted (the unique key
     * (key, scope, scope_id) does not catch a null scope_id in MySQL), so rows an admin already
     * created are kept. Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    private const array OWNER_MIN_AGES = [
        'family.owner_min_age' => [16, 'Youngest age that may create (own) a family and sign up without an invite.'],
        'classroom.owner_min_age' => [18, 'Youngest age of a Classroom family owner (the teacher); checked before a web Classroom purchase.'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];
        $existing = DB::table('settings')->where('scope', 'global')->whereNull('scope_id')
            ->whereIn('key', array_keys(self::OWNER_MIN_AGES))->pluck('key')->all();

        foreach (self::OWNER_MIN_AGES as $key => [$value, $description]) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'scope' => 'global',
                'scope_id' => null,
                'value' => json_encode($value),
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('settings')->insert($rows);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys(self::OWNER_MIN_AGES))->where('scope', 'global')->whereNull('scope_id')->delete();
    }
};
