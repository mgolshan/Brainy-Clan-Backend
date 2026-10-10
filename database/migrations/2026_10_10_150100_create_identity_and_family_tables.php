<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identity: one row per signed-in device, bound to its Sanctum token.
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->unique()
                ->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('name', 100);
            $table->string('platform', 10);
            $table->string('app_version', 20)->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users');
            $table->string('name', 80);
            $table->string('timezone', 64)->default('UTC');
            $table->char('country_code', 2)->default('CA');
            $table->string('tier', 20)->default('free')->comment('Cached from Billing');
            $table->timestamps();
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('nickname', 40);
            $table->unsignedTinyInteger('difficulty_override')->nullable();
            $table->json('accessibility')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['family_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('nickname', 40);
            $table->string('avatar', 30)->nullable();
            $table->unsignedSmallInteger('birth_year');
            $table->unsignedTinyInteger('birth_month');
            $table->timestamp('expires_at');
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['family_id', 'used_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('families');
        Schema::dropIfExists('devices');
    }
};
