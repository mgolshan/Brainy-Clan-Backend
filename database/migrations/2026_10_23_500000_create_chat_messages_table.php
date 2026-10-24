<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Family chat (docs/API.md "Family chat", 2026-10-23).
     *
     * `chat_messages.body` holds the Laravel `encrypted` cast payload (APP_KEY), never plain text;
     * it is null on a deleted message (tombstone). Indexes: (family_id, id) for the id-cursor pages
     * and the unread count, (created_at) for the hourly retention job, unique (family_id, client_id)
     * for idempotent posts. `family_members` gains `chat_enabled` (an admin turns chat off per
     * member) and `chat_last_read_id` (the read marker; only moves forward).
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->nullable()->constrained('family_members')->nullOnDelete();
            $table->text('body')->nullable()->comment('Encrypted with APP_KEY (encrypted cast); null when deleted');
            $table->uuid('client_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['family_id', 'client_id']);
            $table->index(['family_id', 'id']);
            $table->index('created_at');
        });

        Schema::table('family_members', function (Blueprint $table) {
            $table->boolean('chat_enabled')->default(true)->after('accessibility');
            $table->unsignedBigInteger('chat_last_read_id')->nullable()->after('chat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn(['chat_enabled', 'chat_last_read_id']);
        });

        Schema::dropIfExists('chat_messages');
    }
};
