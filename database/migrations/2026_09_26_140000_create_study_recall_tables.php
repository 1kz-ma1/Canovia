<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_recall_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 1000);
            $table->text('answer');
            $table->text('note')->nullable();
            $table->json('tags')->nullable();
            $table->string('fingerprint', 64);
            $table->unsignedInteger('repetitions')->default(0);
            $table->unsignedInteger('lapse_count')->default(0);
            $table->unsignedInteger('interval_days')->default(0);
            $table->decimal('ease_factor', 4, 2)->default(2.50);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['task_id', 'fingerprint']);
            $table->index(['task_id', 'is_active', 'due_at']);
        });

        Schema::create('study_recall_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_recall_item_id')->constrained('study_recall_items')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 191)->nullable()->index();
            $table->uuid('review_request_id')->unique();
            $table->string('rating', 16);
            $table->unsignedInteger('interval_before_days')->default(0);
            $table->unsignedInteger('interval_after_days')->default(0);
            $table->decimal('ease_before', 4, 2)->default(2.50);
            $table->decimal('ease_after', 4, 2)->default(2.50);
            $table->timestamp('due_before')->nullable();
            $table->timestamp('due_after')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['task_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_recall_reviews');
        Schema::dropIfExists('study_recall_items');
    }
};
