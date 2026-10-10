<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Category tree: World Geography -> Capitals -> Europe.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('slug', 80)->unique();
            $table->json('name')->comment('Per locale: {"en": "...", "fr": "..."}');
            $table->string('icon', 30)->nullable();
            $table->integer('sort')->default(0);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort']);
        });

        // One row per challenge type plugin (handler class in app/Modules/ChallengeTypes).
        Schema::create('challenge_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 80);
            $table->string('handler_class');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // One row per AI generation / import run, for review.
        Schema::create('content_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type_key', 40);
            $table->text('prompt')->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type_key', 40);
            $table->string('locale', 10)->default('en');
            $table->unsignedTinyInteger('difficulty')->default(3);
            $table->unsignedTinyInteger('min_age')->nullable();
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->json('payload')->comment('Sent to the app (no answers)');
            $table->json('answer')->comment('Never sent to the app before answering');
            $table->string('status', 20)->default('draft');
            $table->string('source', 20)->default('manual');
            $table->string('source_ref', 120)->nullable()->comment('Stable id from a seed/import, for idempotent re-runs');
            $table->foreignId('content_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status', 'type_key']);
            $table->index(['status', 'locale', 'difficulty']);
            $table->unique('source_ref');
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        Schema::create('content_item_tag', function (Blueprint $table) {
            $table->foreignId('content_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['content_item_id', 'tag_id']);
            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_item_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_batches');
        Schema::dropIfExists('challenge_types');
        Schema::dropIfExists('categories');
    }
};
