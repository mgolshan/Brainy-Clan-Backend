<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Family chat reactions (docs/API.md "Family chat" → "Reactions", 2026-11-11): one reaction per
     * member per message (unique pair), from a fixed set of keys. Both foreign keys cascade, so a
     * message deleted by retention, erasure or family deletion takes its reactions with it.
     *
     * `chat_messages.reactions_changed_at` (microseconds) is the change marker behind
     * `GET /family/chat/reactions?since=`: every set / replace / remove — and a message deletion or a
     * member leaving, which remove reactions — bumps it. Indexed with (family_id, …, id) so a poll is
     * one range scan in (changed_at, id) order.
     */
    public function up(): void
    {
        Schema::create('chat_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->string('key', 20);
            $table->timestamps();

            $table->unique(['chat_message_id', 'member_id']);
            $table->index('member_id');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dateTime('reactions_changed_at', 6)->nullable()->after('deleted_at');

            $table->index(['family_id', 'reactions_changed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'reactions_changed_at', 'id']);
            $table->dropColumn('reactions_changed_at');
        });

        Schema::dropIfExists('chat_message_reactions');
    }
};
