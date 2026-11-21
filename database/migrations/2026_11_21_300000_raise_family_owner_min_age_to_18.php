<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (docs/API.md "Plans and tiers" → "Owner ages", decision 2026-11-21): the
     * global setting `family.owner_min_age` becomes 18, the same as `classroom.owner_min_age`, so
     * creating a family on any plan and self sign-up (email or social) need 18+. The existing
     * global row is updated; it is inserted when missing. Reference caches are flushed after
     * migrations (MigrationsEnded listener).
     */
    private const string KEY = 'family.owner_min_age';

    private const string DESCRIPTION = 'Youngest age that may create (own) a family on any plan and sign up without an invite.';

    public function up(): void
    {
        $this->setMinAge(18, self::DESCRIPTION);
    }

    public function down(): void
    {
        $this->setMinAge(16, 'Youngest age that may create (own) a family and sign up without an invite.');
    }

    private function setMinAge(int $age, string $description): void
    {
        $now = now();
        $query = DB::table('settings')->where('key', self::KEY)->where('scope', 'global')->whereNull('scope_id');

        if ($query->exists()) {
            $query->update(['value' => json_encode($age), 'description' => $description, 'updated_at' => $now]);

            return;
        }

        DB::table('settings')->insert([
            'key' => self::KEY,
            'scope' => 'global',
            'scope_id' => null,
            'value' => json_encode($age),
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
