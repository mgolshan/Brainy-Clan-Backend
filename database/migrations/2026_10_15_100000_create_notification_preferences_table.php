<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notification preferences, one row per family member (docs/API.md, B8). No row = defaults.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_member_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('types')->comment('Notification type => enabled; missing types use the defaults');
            $table->time('quiet_from')->nullable()->comment('Family timezone; null with quiet_to = no quiet hours');
            $table->time('quiet_to')->nullable();
            $table->time('reminder_time')->comment('Daily reminder time, family timezone');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
