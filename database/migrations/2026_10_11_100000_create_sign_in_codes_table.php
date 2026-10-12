<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One-time sign-in codes an admin issues for a member's new device (docs/API.md, B2b).
     * Only an HMAC of the code is stored (keyed with APP_KEY), so a database leak alone never
     * reveals a usable code; the plain code is shown once, when it is issued.
     */
    public function up(): void
    {
        Schema::create('sign_in_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('family_members')->cascadeOnDelete();
            $table->char('code_hash', 64)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('device_id')->nullable()->comment('Device signed in with this code')->constrained()->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->comment('Replaced by a newer code');
            $table->timestamps();

            $table->index(['member_id', 'used_at', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sign_in_codes');
    }
};
