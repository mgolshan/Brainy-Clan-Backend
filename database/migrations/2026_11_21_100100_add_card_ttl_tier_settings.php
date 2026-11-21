<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (B12 step 3, docs/API.md "Classroom tools" → "Code cards"): the tier setting
     * `sign_in_codes.card_ttl_days` — 14 days for the classroom tier, the only tier with code cards
     * (other tiers fall back to the resolver default). Insert-or-ignore on the unique key
     * (key, scope, scope_id), so a row an admin already created is kept. Reference caches are flushed
     * after migrations (MigrationsEnded listener).
     */
    private const array CARD_TTL_DAYS = [
        'classroom' => [14, 'Classroom plan: days a printed sign-in code card stays valid (one-time use).'],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        foreach (self::CARD_TTL_DAYS as $tier => [$value, $description]) {
            $rows[] = [
                'key' => 'sign_in_codes.card_ttl_days',
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
        DB::table('settings')->where('key', 'sign_in_codes.card_ttl_days')->where('scope', 'tier')->delete();
    }
};
