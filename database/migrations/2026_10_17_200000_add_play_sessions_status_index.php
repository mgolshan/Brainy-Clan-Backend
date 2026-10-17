<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The hourly idle-session sweep (`play:abandon-idle-sessions`) looks up unfinished sessions
     * by status and start time across all members.
     */
    public function up(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->index(['status', 'started_at'], 'play_sessions_status_started_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropIndex('play_sessions_status_started_at_index');
        });
    }
};
