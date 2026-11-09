<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support messages (docs/API.md "Support messages", docs/PLAN.md "Support channel without a
     * public mailto", 2026-11-08): written in the app by family admins (`source` app, linked to the
     * user and family) or on the website's /support form (`source` web, name + email only). Staff
     * reply from the Filament "Support inbox"; replies are kept in `support_message_replies`.
     *
     * `client_id` is unique per user (app retries are idempotent; web messages have none). The
     * user link is deleted with the account (EraseUser deletes the rows); the family link becomes
     * null if a family row ever goes. Retention (`support:prune`): closed 1 year after closing,
     * anything 2 years after the last activity.
     */
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->string('source', 8);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('topic', 16);
            $table->text('message');
            $table->string('status', 16)->default('new');
            $table->string('app_version', 20)->nullable();
            $table->string('platform', 10)->nullable();
            $table->string('device', 80)->nullable();
            $table->string('client_id', 36)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_id']);
            $table->index(['status', 'created_at']);
            $table->index('last_activity_at');
            $table->index('closed_at');
        });

        Schema::create('support_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('support_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_message_replies');
        Schema::dropIfExists('support_messages');
    }
};
