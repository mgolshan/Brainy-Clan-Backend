<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B10: web checkout (Paddle) and the affiliate program.
 *
 * - `subscriptions` holds one row per family **and provider** (`revenuecat`, `paddle`), so a store
 *   plan and a web plan never overwrite each other; the tier is `family` while any row is entitled.
 *   Paddle rows keep the Paddle subscription and customer ids.
 * - `families.first_paid_at`: the family's first paid transaction (any source) — locks the referral
 *   code and starts the commission window.
 * - `web_checkout_requests`: the emailed one-time checkout links of the website subscribe form.
 * - `affiliates`, `affiliate_codes`, `family_attributions`, `commission_entries` (append-only
 *   amounts; only the status moves pending → payable → paid) and `commission_payouts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add the composite unique first: it starts with family_id, so the foreign key keeps an index.
            $table->unique(['family_id', 'provider']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['family_id']);
            $table->string('external_id', 100)->nullable()->after('app_user_id')->comment('Paddle subscription id (sub_…)');
            $table->string('customer_id', 100)->nullable()->after('external_id')->comment('Paddle customer id (ctm_…)');
            $table->index('external_id');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->timestamp('first_paid_at')->nullable()->after('tier')->comment('First paid transaction (store or web)');
        });

        Schema::create('web_checkout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email_hash', 64)->comment('HMAC of the email, never the plain address');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan', 20);
            $table->string('affiliate_code', 20)->nullable();
            $table->string('affiliate_source', 20)->nullable()->comment('link | web_checkout');
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('status', 20)->comment('pending | opened | superseded | ineligible | no_account');
            $table->string('reason', 40)->nullable()->comment('Why the email could not get a link');
            $table->string('locale', 5)->default('en');
            $table->string('transaction_id', 100)->nullable()->comment('Paddle transaction created from the link');
            $table->timestamp('expires_at');
            $table->timestamp('opened_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['email_hash', 'status']);
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('contact_email')->nullable();
            $table->text('contact_note')->nullable();
            $table->text('payout_method_note')->nullable();
            $table->decimal('commission_rate', 5, 4)->default(0.25);
            $table->unsignedSmallInteger('commission_months')->default(12);
            $table->string('status', 20)->default('active')->comment('active | paused | ended');
            $table->timestamps();
        });

        Schema::create('affiliate_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique()->comment('Stored upper case; matched case-insensitively');
            $table->json('store_offer_codes')->nullable()->comment('Apple offer codes / Google promo codes RevenueCat reports as offer_code');
            $table->string('paddle_discount_id', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('family_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('affiliate_id')->constrained()->restrictOnDelete();
            $table->string('source', 20)->comment('link | code | offer_code | web_checkout');
            $table->timestamp('attributed_at');
            $table->timestamp('locked_at')->nullable()->comment('First paid transaction; only staff can change it afterwards');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->timestamps();

            $table->index('affiliate_id');
        });

        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->restrictOnDelete();
            $table->char('month', 7)->comment('YYYY-MM of the entries');
            $table->char('currency', 3);
            $table->bigInteger('amount_minor');
            $table->unsignedInteger('entries_count');
            $table->timestamp('paid_at');
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'month']);
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->restrictOnDelete();
            $table->foreignId('affiliate_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->comment('revenuecat | paddle');
            $table->string('source_event_id', 120)->unique();
            $table->string('source_reference', 120)->nullable()->comment('Store / Paddle transaction id');
            $table->string('kind', 30)->comment('sale | refund | chargeback | chargeback_reverse');
            $table->timestamp('occurred_at');
            $table->char('currency', 3);
            $table->bigInteger('gross_minor');
            $table->bigInteger('tax_minor');
            $table->bigInteger('fee_minor');
            $table->bigInteger('net_minor');
            $table->decimal('commission_rate', 5, 4);
            $table->bigInteger('commission_minor');
            $table->bigInteger('net_usd_minor')->nullable();
            $table->bigInteger('commission_usd_minor')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending | payable | paid');
            $table->timestamp('payable_at');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained('commission_payouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'payable_at']);
            $table->index(['affiliate_id', 'status', 'occurred_at']);
            $table->index('source_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('family_attributions');
        Schema::dropIfExists('affiliate_codes');
        Schema::dropIfExists('affiliates');
        Schema::dropIfExists('web_checkout_requests');

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('first_paid_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropColumn(['external_id', 'customer_id']);
            $table->unique('family_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['family_id', 'provider']);
        });
    }
};
