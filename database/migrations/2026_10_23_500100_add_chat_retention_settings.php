<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data migration (family chat, 2026-10-23): tier settings `chat.retention_days` — free 7 days,
     * Family plan 365 days. Rows an admin already created are kept (insert-or-ignore on the unique
     * key, scope, scope_id). Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    public function up(): void
    {
        $now = now();

        DB::table('settings')->insertOrIgnore([
            ['key' => 'chat.retention_days', 'scope' => 'tier', 'scope_id' => 'free', 'value' => json_encode(7), 'description' => 'Free tier: family chat messages are deleted after this many days.', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'chat.retention_days', 'scope' => 'tier', 'scope_id' => 'family', 'value' => json_encode(365), 'description' => 'Family plan: family chat messages are deleted after this many days.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'chat.retention_days')->delete();
    }
};
