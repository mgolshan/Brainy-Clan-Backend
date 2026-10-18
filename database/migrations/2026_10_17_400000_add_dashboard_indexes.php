<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin dashboard (`App\Filament\Support\DashboardMetrics`) aggregates the last 7/30 days
     * of plays and points across all families; without these, EXPLAIN showed full scans of both
     * tables. Covering indexes make the range queries index-only.
     */
    public function up(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->index(['started_at', 'member_id', 'category_id'], 'play_sessions_started_at_member_id_category_id_index');
        });

        Schema::table('point_entries', function (Blueprint $table) {
            $table->index(['created_at', 'amount'], 'point_entries_created_at_amount_index');
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropIndex('play_sessions_started_at_member_id_category_id_index');
        });

        Schema::table('point_entries', function (Blueprint $table) {
            $table->dropIndex('point_entries_created_at_amount_index');
        });
    }
};
