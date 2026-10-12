<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goal progress is always computed from the ledger (one answer can count toward several
     * plans), so the per-period cache and the `goal_period_id` columns were never filled or read.
     * Dropped as dead schema (docs/PROGRESS.md, B6).
     */
    public function up(): void
    {
        Schema::dropIfExists('member_period_totals');

        Schema::table('point_entries', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'goal_period_id']);
        });

        Schema::table('point_entries', function (Blueprint $table) {
            $table->dropColumn('goal_period_id');
        });

        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropIndex(['goal_period_id']);
        });

        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropColumn('goal_period_id');
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('goal_period_id')->nullable()->index()->after('member_id');
        });

        Schema::table('point_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('goal_period_id')->nullable()->after('member_id');
            $table->index(['family_id', 'goal_period_id']);
        });

        Schema::create('member_period_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->unsignedBigInteger('goal_period_id');
            $table->integer('points')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->timestamps();

            $table->unique(['member_id', 'goal_period_id']);
            $table->index('goal_period_id');
        });
    }
};
