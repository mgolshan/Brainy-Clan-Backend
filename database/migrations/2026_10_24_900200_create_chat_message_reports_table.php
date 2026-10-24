<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Family chat reports (docs/API.md "Family chat", 2026-10-24; Apple guideline 1.2): a member
     * reports another member's message to the family's admins. One row per (message, reporter) —
     * the unique pair makes reports idempotent. No text is stored here. Both foreign keys cascade,
     * so a message deleted by retention, account erasure or family deletion takes its reports with it.
     */
    public function up(): void
    {
        Schema::create('chat_message_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('reporter_member_id')->constrained('family_members')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['chat_message_id', 'reporter_member_id']);
            $table->index('reporter_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reports');
    }
};
