<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->string('request_hash', 64)->unique();
            $table->string('exercise_title', 120)->nullable();
            $table->json('questions');
            $table->json('answers');
            $table->json('assessment');
            $table->unsignedTinyInteger('score_percent');
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->unsignedTinyInteger('recommended_task_progress_percent');
            $table->unsignedTinyInteger('progress_before_percent')->nullable();
            $table->unsignedTinyInteger('progress_after_percent')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamp('applied_at')->nullable()->index();
            $table->timestamps();

            $table->index(['plan_id', 'task_id', 'created_at'], 'study_attempt_plan_task_created_idx');
            $table->index(['user_id', 'task_id', 'created_at'], 'study_attempt_user_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_practice_attempts');
    }
};
