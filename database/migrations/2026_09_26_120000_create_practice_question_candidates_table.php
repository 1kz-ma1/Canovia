<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_question_candidates', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->string('provider', 32)->default('native_ai')->index();
            $table->string('model', 120)->nullable();
            $table->string('exam_profile_key', 80)->nullable()->index();
            $table->foreignId('first_practice_question_demand_id')
                ->nullable()
                ->constrained('practice_question_demands')
                ->nullOnDelete();
            $table->foreignId('latest_practice_question_demand_id')
                ->nullable()
                ->constrained('practice_question_demands')
                ->nullOnDelete();
            $table->foreignId('native_ai_run_id')
                ->nullable()
                ->constrained('native_ai_runs')
                ->nullOnDelete();
            $table->json('question_payload');
            $table->json('review_hints')->nullable();
            $table->unsignedInteger('generation_count')->default(1);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('review_data')->nullable();
            $table->foreignId('promoted_question_pack_id')
                ->nullable()
                ->constrained('question_packs')
                ->nullOnDelete();
            $table->foreignId('promoted_question_id')
                ->nullable()
                ->constrained('questions')
                ->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_seen_at'], 'practice_candidate_status_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_question_candidates');
    }
};
