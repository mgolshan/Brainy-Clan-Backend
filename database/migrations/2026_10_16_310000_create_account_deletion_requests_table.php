<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account deletion requests from the public web page `/account/delete` (Google Play
     * requirement). No plain email is stored: only a keyed hash, the matched user (if any) and the
     * hash of the one-time confirmation token.
     */
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->char('email_hash', 64)->index()->comment('HMAC-SHA256 of the lowercased email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Null when no account has this email');
            $table->char('token_hash', 64)->nullable()->unique()->comment('SHA-256 of the emailed confirmation token');
            $table->string('status', 20)->default('pending')->comment('pending | completed | blocked | expired | superseded | no_account');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
