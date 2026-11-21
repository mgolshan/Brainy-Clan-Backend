<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (B12 step 3, docs/API.md "Classroom tools" → "Adult seats"): the tier setting
     * `limits.max_adults` — null (unlimited) for free, family and big_clan, 2 for classroom (teacher
     * + co-teacher). Insert-or-ignore on the unique key (key, scope, scope_id), so rows an admin
     * already created are kept. Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    private const array MAX_ADULTS = [
        'free' => [null, 'Free tier: no limit on adult members (null); the member limit applies.'],
        'family' => [null, 'Family plan: no limit on adult members (null); the member limit applies.'],
        'big_clan' => [null, 'Big Clan plan: no limit on adult members (null); the member limit applies.'],
        'classroom' => [2, 'Classroom plan: 2 adult seats (teacher + co-teacher); everyone else is a student.'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (self::MAX_ADULTS as $tier => [$value, $description]) {
            $rows[] = [
                'key' => 'limits.max_adults',
                'scope' => 'tier',
                'scope_id' => $tier,
                'value' => json_encode($value),
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('settings')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'limits.max_adults')->where('scope', 'tier')->delete();
    }
};
