<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing (B8): one subscription row per family, fed only by RevenueCat webhooks, and the
 * webhook inbox that makes every webhook safe to receive twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 30)->default('revenuecat');
            $table->string('app_user_id', 100)->comment('RevenueCat app user id, family-{id}');
            $table->string('entitlement', 50)->default('family');
            $table->string('status', 20)->comment('active | grace | expired');
            $table->string('store', 30)->nullable()->comment('app_store | play_store | stripe | promotional | …');
            $table->string('product_id')->nullable();
            $table->string('period_type', 20)->nullable()->comment('normal | trial | intro | prepaid');
            $table->string('environment', 20)->nullable()->comment('PRODUCTION | SANDBOX');
            $table->boolean('will_renew')->default(true);
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_expires_at')->nullable();
            $table->unsignedBigInteger('last_event_ms')->nullable()->comment('event_timestamp_ms of the last applied event');
            $table->string('last_event_type', 40)->nullable();
            $table->json('raw')->nullable()->comment('Last applied RevenueCat event');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_id', 100);
            $table->string('type', 40)->nullable();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('outcome', 30)->nullable()->comment('applied | ignored | stale | failed');
            $table->string('note')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('subscriptions');
    }
};
