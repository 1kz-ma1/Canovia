<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_recall_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 191)->nullable()->index();
            $table->string('source_type', 32);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->longText('source_text')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('native_ai_run_id')->nullable()->index();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'status', 'created_at']);
        });

        Schema::create('study_recall_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_recall_source_id')->constrained('study_recall_sources')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promoted_item_id')->nullable()->constrained('study_recall_items')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prompt', 1000);
            $table->text('answer');
            $table->text('note')->nullable();
            $table->json('tags')->nullable();
            $table->text('source_excerpt')->nullable();
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('status', 24)->default('pending');
            $table->string('fingerprint', 64);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'fingerprint']);
            $table->index(['task_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_recall_candidates');
        Schema::dropIfExists('study_recall_sources');
    }
};
