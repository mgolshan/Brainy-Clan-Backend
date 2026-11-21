<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classroom tools (B12 step 3, docs/API.md "Classroom tools"): the setup state the Family object
     * shows. `season_started_at` is set by "Archive & start a new year" with `clear.points`: family
     * totals count ledger entries from it, so the append-only ledger never changes.
     * `codes_issued_at` is set when code cards are issued.
     */
    public function up(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->timestamp('season_started_at')->nullable()->after('chat_mode')
                ->comment('Points season start (Classroom new year); family totals count from here');
            $table->timestamp('codes_issued_at')->nullable()->after('season_started_at')
                ->comment('Last time Classroom sign-in code cards were issued');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn(['season_started_at', 'codes_issued_at']);
        });
    }
};
