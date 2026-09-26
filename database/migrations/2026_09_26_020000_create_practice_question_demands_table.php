<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_question_demands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_practice_session_id')->nullable()->constrained('study_practice_sessions')->nullOnDelete();
            $table->foreignId('question_pack_id')->nullable()->constrained('question_packs')->nullOnDelete();
            $table->uuid('prepare_request_id')->unique();
            $table->string('strategy_key', 64)->nullable();
            $table->string('exam_profile_key', 80)->nullable()->index();
            $table->string('assembly_mode', 32)->index();
            $table->string('generation_provider', 32)->nullable()->index();
            $table->unsignedTinyInteger('requested_count')->default(0);
            $table->unsignedTinyInteger('bank_selected_count')->default(0);
            $table->unsignedTinyInteger('generated_requested_count')->default(0);
            $table->unsignedTinyInteger('generated_count')->default(0);
            $table->json('focus_topics')->nullable();
            $table->json('coverage')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'task_id', 'created_at'], 'practice_demand_plan_task_created_idx');
            $table->index(['assembly_mode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_question_demands');
    }
};
