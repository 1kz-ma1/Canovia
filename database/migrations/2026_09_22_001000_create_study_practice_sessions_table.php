<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->uuid('session_token')->unique();
            $table->uuid('prepare_request_id')->unique();
            $table->string('status', 32)->default('preparing')->index();
            $table->string('strategy', 48)->index();
            $table->string('strategy_version', 32)->default('v1');
            $table->string('selector_type', 48)->index();
            $table->string('selector_version', 48)->default('v1');
            $table->string('question_provider', 48)->index();
            $table->string('question_provider_mode', 24)->default('handoff');
            $table->string('assessment_provider', 48)->default('external_ai');
            $table->string('assessment_provider_mode', 24)->default('handoff');
            $table->json('selection_context')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('selected_questions')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['plan_id', 'task_id', 'created_at'], 'study_session_plan_task_created_idx');
            $table->index(['user_id', 'task_id', 'created_at'], 'study_session_user_task_created_idx');
        });

        Schema::table('study_practice_attempts', function (Blueprint $table) {
            $table->foreignId('study_practice_session_id')
                ->nullable()
                ->after('id')
                ->constrained('study_practice_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('study_practice_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('study_practice_session_id');
        });

        Schema::dropIfExists('study_practice_sessions');
    }
};
