<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B7: generated items (Sudoku, Math Sprint) and item points.
 *
 * - session_items: `type_key` and `points` (snapshot of what a correct answer is worth) for every
 *   item; for generated items (`content_item_id` null) the sub-scope `category_id` plus a
 *   `payload` / `answer` snapshot built from `seed` (the answer never leaves the server).
 * - categories: `generators`, the generated challenge types a category offers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_items', function (Blueprint $table) {
            $table->string('type_key', 40)->nullable()->after('content_item_id');
            $table->foreignId('category_id')->nullable()->after('type_key')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('points')->default(1)->after('is_redemption');
            $table->json('payload')->nullable()->after('points')->comment('Generated items: sent to the app');
            $table->json('answer')->nullable()->after('payload')->comment('Generated items: never sent before answering');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->json('generators')->nullable()->after('is_active')
                ->comment('Generated types: [{"type","options","min_difficulty","max_difficulty"}]');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('generators');
        });

        Schema::table('session_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['type_key', 'points', 'payload', 'answer']);
        });
    }
};
