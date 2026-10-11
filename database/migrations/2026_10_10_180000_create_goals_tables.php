<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a family admin configures.
        Schema::create('goal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('mode', 20)->comment('competition | cooperation');
            $table->string('period_type', 20)->comment('daily | weekly | monthly');
            $table->unsignedInteger('target_points');
            $table->string('reward_text', 255)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['family_id', 'is_active']);
        });

        // A scope at any tree level includes its subtree.
        Schema::create('goal_plan_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->unique(['goal_plan_id', 'category_id']);
        });

        Schema::create('goal_plan_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->unsignedInteger('target_override')->nullable();

            $table->unique(['goal_plan_id', 'member_id']);
        });

        // One concrete window. Targets are snapshotted when the period opens, so plan edits
        // apply from the next period; `result` is written when the period closes.
        Schema::create('goal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('open')->comment('open | closed');
            $table->unsignedInteger('target_points');
            $table->json('member_targets')->comment('{member_id: target} snapshot');
            $table->json('result')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['goal_plan_id', 'starts_at']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_periods');
        Schema::dropIfExists('goal_plan_members');
        Schema::dropIfExists('goal_plan_scopes');
        Schema::dropIfExists('goal_plans');
    }
};
