<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B12 step 1: the emailed web checkout link carries the plan tier (`family`, `big_clan`,
     * `classroom`) next to the billing period, so the Paddle transaction uses that tier's price.
     * Existing rows were all Family plan requests.
     */
    public function up(): void
    {
        Schema::table('web_checkout_requests', function (Blueprint $table) {
            $table->string('tier', 20)->default('family')->after('family_id')->comment('family | big_clan | classroom');
        });
    }

    public function down(): void
    {
        Schema::table('web_checkout_requests', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
