<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding / FTUE (B12 step 4, docs/API.md "Onboarding (FTUE)"): one row per user. `steps` maps
     * each done step key to its first `done_at` (manual steps from the app, derived steps the first
     * time the server sees them true); `dismissed_at` hides the "Getting started" checklist.
     */
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('steps')->comment('Done step keys → first done_at (ISO 8601 UTC)');
            $table->timestamp('dismissed_at')->nullable()->comment('When the user hid the Getting started checklist');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
