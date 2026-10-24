<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B11 staff roles: `users.staff_role` (null = no admin access, `admin` = everything, `support` =
     * read-only) replaces the all-or-nothing `is_staff` flag. Existing staff become `admin`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_role', 16)->nullable()->after('locale')
                ->comment('Filament admin role: null (none), admin (everything), support (read-only)');
        });

        DB::table('users')->where('is_staff', true)->update(['staff_role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('locale')->comment('May sign in to the Filament admin');
        });

        DB::table('users')->whereNotNull('staff_role')->update(['is_staff' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('staff_role');
        });
    }
};
