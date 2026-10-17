<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes for two hot lookups found in the session 15/16 hardening pass:
     * - `devices.push_token`: looked up by value when a token moves to another device
     *   (`SetPushToken`) and when Expo reports invalid tokens (`DeliverPushNotification`).
     * - `play_sessions (member_id, started_at)`: the daily reminder's "started a session since
     *   local midnight" check (`SendDailyReminders`) and per-member session history by date.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->index('push_token', 'devices_push_token_index');
        });

        Schema::table('play_sessions', function (Blueprint $table) {
            $table->index(['member_id', 'started_at'], 'play_sessions_member_id_started_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropIndex('play_sessions_member_id_started_at_index');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_push_token_index');
        });
    }
};
