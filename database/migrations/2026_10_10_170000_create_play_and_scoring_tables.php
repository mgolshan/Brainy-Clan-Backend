<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Play: one sitting.
        Schema::create('play_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->unsignedBigInteger('goal_period_id')->nullable()->index();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('target_points');
            $table->unsignedSmallInteger('blunder_cap');
            $table->unsignedSmallInteger('mistakes')->default(0);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('second_chances_used')->default(0);
            $table->boolean('redemption_failed')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });

        Schema::create('session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seed', 64)->nullable()->comment('For generated puzzles (B7)');
            $table->unsignedSmallInteger('position');
            $table->boolean('is_redemption')->default(false);
            $table->json('response')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('points_awarded')->default(0);
            $table->unsignedInteger('time_ms')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['play_session_id', 'position']);
        });

        Schema::create('second_chances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->string('method', 20)->comment('free | ad | redemption');
            $table->string('reward_ref')->nullable()->comment('AdMob reward id (verified in B8)');
            $table->timestamp('granted_at');

            $table->index(['member_id', 'method', 'granted_at']);
        });

        Schema::create('member_item_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('times_seen')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->timestamp('last_seen_at')->nullable();

            $table->unique(['member_id', 'content_item_id']);
        });

        // Scoring: the ledger. Rows are only ever inserted, never updated or deleted.
        Schema::create('point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->unsignedBigInteger('goal_period_id')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('play_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('session_item_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->integer('amount');
            $table->string('reason', 40);
            $table->timestamp('created_at');

            $table->index(['family_id', 'goal_period_id']);
            $table->index(['member_id', 'created_at']);
            $table->index(['family_id', 'created_at']);
        });

        // Cache rebuilt from the ledger (filled per goal period from B6).
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

        Schema::create('streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->unique()->constrained('family_members')->cascadeOnDelete();
            $table->unsignedInteger('current')->default(0);
            $table->unsignedInteger('longest')->default(0);
            $table->date('last_active_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaks');
        Schema::dropIfExists('member_period_totals');
        Schema::dropIfExists('point_entries');
        Schema::dropIfExists('member_item_history');
        Schema::dropIfExists('second_chances');
        Schema::dropIfExists('session_items');
        Schema::dropIfExists('play_sessions');
    }
};
