<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'status', 'sort_order'], 'task_milestone_status_sort_idx');
        });

        Schema::create('task_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->foreignId('milestone_id')->nullable()->constrained('task_milestones')->nullOnDelete();
            $table->string('source', 32);
            $table->string('type', 64);
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('confidence')->default(100);
            $table->unsignedInteger('observed_duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->char('fingerprint', 64)->unique();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['plan_id', 'task_id', 'occurred_at'], 'task_evidence_plan_task_time_idx');
            $table->index(['user_id', 'task_id', 'occurred_at'], 'task_evidence_user_task_time_idx');
            $table->index(['source', 'provider'], 'task_evidence_source_provider_idx');
        });

        Schema::create('task_progress_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_evidence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->string('source', 32)->default('rule');
            $table->string('status', 24)->default('proposed');
            $table->unsignedTinyInteger('progress_before_percent');
            $table->unsignedTinyInteger('progress_after_percent');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->char('decision_key', 64)->unique();
            $table->timestamp('applied_at')->nullable()->index();
            $table->timestamps();

            $table->index(['task_id', 'status', 'created_at'], 'task_progress_decision_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_progress_decisions');
        Schema::dropIfExists('task_evidences');
        Schema::dropIfExists('task_milestones');
    }
};
