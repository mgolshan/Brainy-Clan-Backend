<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Translations of one item share a `translation_group`. `cross_locale` = false marks a
     * language-bound English item (spelling, idioms, English words) that members of another
     * locale never get. The index serves the picker's NOT EXISTS (same group, member locale, published).
     */
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->string('translation_group', 64)->nullable()->after('locale');
            $table->boolean('cross_locale')->default(true)->after('translation_group');
            $table->index(['translation_group', 'locale', 'status'], 'content_items_translation_group_locale_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropIndex('content_items_translation_group_locale_status_index');
            $table->dropColumn(['translation_group', 'cross_locale']);
        });
    }
};
