<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->string('source', 32)->index();
            $table->string('type', 64)->index();
            $table->string('external_key', 191)->nullable();
            $table->decimal('confidence', 5, 4)->default(1);
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'source', 'external_key'], 'task_evidence_source_key_unique');
            $table->index(['plan_id', 'occurred_at'], 'task_evidence_plan_occurred_idx');
            $table->index(['task_id', 'occurred_at'], 'task_evidence_task_occurred_idx');
        });

        Schema::create('task_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('evidence_requirement')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'sort_order'], 'task_milestone_task_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_milestones');
        Schema::dropIfExists('task_evidences');
    }
};
