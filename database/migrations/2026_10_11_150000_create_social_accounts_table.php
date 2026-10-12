<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Google / Facebook accounts linked to a user for social sign-in (docs/API.md, B6b).
     * One account per provider per user; a provider account belongs to exactly one user.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->comment('google | facebook');
            $table->string('provider_user_id', 191)->comment('Google `sub` / Facebook user id');
            $table->string('email')->nullable()->comment('Provider-verified email at the last sign-in');
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
