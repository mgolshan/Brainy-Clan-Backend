<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Policy: age bands carry the rules for an age range (optionally per country).
        Schema::create('age_bands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30);
            $table->string('name', 60);
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->char('country_code', 2)->nullable()->comment('null = applies everywhere');
            $table->json('policy');
            $table->timestamps();

            $table->unique(['code', 'country_code']);
            $table->index(['country_code', 'min_age']);
        });

        // Policy: feature flags, limits and remote config, scoped global / tier / age_band.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('scope', 20)->default('global');
            $table->string('scope_id', 50)->nullable()->comment('tier key or age band code; null for global');
            $table->json('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['key', 'scope', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('age_bands');
    }
};
