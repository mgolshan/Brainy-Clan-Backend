<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Account deletion (B6c, docs/API.md "Account deletion"):
     * - `social_accounts.refresh_token`: Apple refresh token (encrypted) from the sign-in
     *   `authorization_code`, revoked with Apple on unlink and deletion; `token_client_id` is the
     *   client id (bundle ID / Services ID) the code was issued for.
     * - `users.erased_at`: the account was deleted; personal data is erased but the row stays
     *   because the append-only points ledger references its memberships.
     * - `families.deleted_at`: the family was deleted (row kept for the ledger and billing records).
     */
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->text('refresh_token')->nullable()->after('email')->comment('Apple only, encrypted');
            $table->string('token_client_id', 191)->nullable()->after('refresh_token');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('erased_at')->nullable();
        });

        Schema::table('families', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('families', fn (Blueprint $table) => $table->dropColumn('deleted_at'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('erased_at'));
        Schema::table('social_accounts', fn (Blueprint $table) => $table->dropColumn(['refresh_token', 'token_client_id']));
    }
};
