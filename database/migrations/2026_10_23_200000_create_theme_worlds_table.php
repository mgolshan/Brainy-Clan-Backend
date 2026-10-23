<?php

use App\Modules\Policy\Models\ThemeWorld;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The theme world catalog (worlds phase 2, 2026-10-23), filled with the contract's default
     * catalog so existing databases get it without a seeder run (keys already present are
     * kept). Reference caches are flushed after migrations (MigrationsEnded listener).
     */
    public function up(): void
    {
        if (! Schema::hasTable('theme_worlds')) {
            Schema::create('theme_worlds', function (Blueprint $table) {
                $table->id();
                $table->string('key', 40)->unique();
                $table->unsignedInteger('sort')->default(0);
                $table->string('unlock_type', 20);
                $table->unsignedInteger('required')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        DB::table('theme_worlds')->insertOrIgnore(array_map(
            fn (array $row): array => [...$row, 'created_at' => $now, 'updated_at' => $now],
            ThemeWorld::defaultRows(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_worlds');
    }
};
