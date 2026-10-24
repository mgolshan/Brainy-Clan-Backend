<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B11: daily counters of rejected provider callbacks (AdMob SSV with a bad signature or
     * unusable data). One row per provider, reason and UTC day — no payloads, no personal data,
     * so it stays tiny however many bad callbacks arrive. Shown on the admin dashboard.
     */
    public function up(): void
    {
        Schema::create('webhook_rejections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('reason', 40);
            $table->date('day');
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'reason', 'day']);
            $table->index(['provider', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_rejections');
    }
};
