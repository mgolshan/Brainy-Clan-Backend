<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI batch generator (B7): what was asked for (locale, difficulty and age range, count, admin
 * note), what came back (token usage, dropped/skipped items, error) and when it ran.
 * Status flow: queued → running → review → done | failed (`draft` stays for hand-made batches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_batches', function (Blueprint $table) {
            $table->string('locale', 10)->default('en')->after('type_key');
            $table->string('variant', 30)->nullable()->after('locale')->comment('word_puzzle variant');
            $table->unsignedTinyInteger('min_difficulty')->nullable()->after('variant');
            $table->unsignedTinyInteger('max_difficulty')->nullable()->after('min_difficulty');
            $table->unsignedTinyInteger('min_age')->nullable()->after('max_difficulty');
            $table->unsignedTinyInteger('max_age')->nullable()->after('min_age');
            $table->unsignedSmallInteger('requested_count')->nullable()->after('max_age');
            $table->text('note')->nullable()->after('requested_count');
            $table->unsignedInteger('input_tokens')->nullable()->after('model');
            $table->unsignedInteger('output_tokens')->nullable()->after('input_tokens');
            $table->json('skipped')->nullable()->after('item_count')->comment('{"invalid": [...], "duplicates": [...]}');
            $table->text('error')->nullable()->after('status');
            $table->timestamp('started_at')->nullable()->after('created_by');
            $table->timestamp('finished_at')->nullable()->after('started_at');

            $table->index(['status', 'id']);
        });

        DB::table('content_batches')->where('status', 'generating')->update(['status' => 'running']);
        DB::table('content_batches')->whereIn('status', ['approved', 'rejected'])->update(['status' => 'done']);
    }

    public function down(): void
    {
        Schema::table('content_batches', function (Blueprint $table) {
            $table->dropIndex(['status', 'id']);
            $table->dropColumn([
                'locale', 'variant', 'min_difficulty', 'max_difficulty', 'min_age', 'max_age', 'requested_count',
                'note', 'input_tokens', 'output_tokens', 'skipped', 'error', 'started_at', 'finished_at',
            ]);
        });
    }
};
