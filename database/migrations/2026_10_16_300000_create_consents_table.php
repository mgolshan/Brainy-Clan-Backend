<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of legal consents (docs/API.md "Legal, consent and privacy (B9)"):
     * `terms` / `privacy` acceptances by a user, and `parental` consent an admin gives for a child
     * invite (linked to the member once the child joins). Rows are never updated except to link the
     * joined member, and they stay after account erasure as proof of consent.
     */
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Who consented');
            $table->string('type', 20)->comment('terms | privacy | parental');
            $table->string('version', 40)->comment('Document version accepted (parental: the privacy version)');
            $table->foreignId('subject_member_id')->nullable()->constrained('family_members')->nullOnDelete()
                ->comment('Parental: the child member, once joined');
            $table->foreignId('subject_invite_id')->nullable()->constrained('invites')->nullOnDelete()
                ->comment('Parental: the child invite');
            $table->timestamp('accepted_at');
            $table->char('ip_hash', 64)->nullable()->comment('HMAC-SHA256 of the IP with the app key');

            $table->index(['user_id', 'type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
