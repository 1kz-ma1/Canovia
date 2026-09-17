<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('artifact_type', 24)->default('file');
            $table->string('title');
            $table->text('url');
            $table->string('version_label', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'provider']);
            $table->index(['plan_id', 'updated_at']);
        });

        Schema::create('plan_artifact_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_artifact_id')->constrained('plan_artifacts')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_artifact_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_artifact_task');
        Schema::dropIfExists('plan_artifacts');
    }
};
