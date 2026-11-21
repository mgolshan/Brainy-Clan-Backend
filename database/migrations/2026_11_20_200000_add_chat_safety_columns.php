<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat safety (B12 step 2, docs/API.md "Family chat"): the family's profanity filter (on by
     * default, every plan) and chat mode (`normal` | `announcements` | `off`, Classroom only via the
     * tier setting `chat.modes_allowed`); messages remember whether masking changed them. The tier
     * settings use insert-or-ignore on (key, scope, scope_id), so rows an admin already created are
     * kept. Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    private const array MODES_ALLOWED = [
        'free' => [false, 'Free tier: chat modes (announcements only, chat off) are a Classroom tool.'],
        'family' => [false, 'Family plan: chat modes (announcements only, chat off) are a Classroom tool.'],
        'big_clan' => [false, 'Big Clan plan: chat modes (announcements only, chat off) are a Classroom tool.'],
        'classroom' => [true, 'Classroom plan: the teacher can set the chat to announcements only or turn it off.'],
    ];

    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->boolean('chat_filter')->default(true)->after('tier');
            $table->string('chat_mode', 20)->default('normal')->after('chat_filter');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->boolean('filtered')->default(false)->after('body');
        });

        $now = now();
        $rows = [];

        foreach (self::MODES_ALLOWED as $tier => [$value, $description]) {
            $rows[] = [
                'key' => 'chat.modes_allowed',
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
        DB::table('settings')->where('key', 'chat.modes_allowed')->where('scope', 'tier')->delete();

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn('filtered');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn(['chat_filter', 'chat_mode']);
        });
    }
};
