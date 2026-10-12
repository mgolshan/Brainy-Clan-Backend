<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `DELETE /goals/{plan}` is a soft delete (docs/API.md 2026-10-11): the plan disappears from the
     * API, its periods and history stay in the database. Pausing stays `is_active: false`.
     */
    public function up(): void
    {
        Schema::table('goal_plans', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('goal_plans', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
