<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rewarded-ad rewards confirmed by AdMob server-side verification (docs/API.md "Rewarded ads"):
 * one row per AdMob `transaction_id`; a second chance with `method: "ad"` consumes one (`used_at`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id', 191)->unique()->comment('AdMob transaction id');
            $table->string('reward_ref', 100)->index()->comment('App-generated id from the SSV custom data');
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->foreignId('play_session_id')->constrained()->cascadeOnDelete();
            $table->string('ad_unit', 100)->nullable();
            $table->string('reward_item', 100)->nullable();
            $table->unsignedInteger('reward_amount')->nullable();
            $table->json('payload')->comment('All SSV query parameters');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_rewards');
    }
};
