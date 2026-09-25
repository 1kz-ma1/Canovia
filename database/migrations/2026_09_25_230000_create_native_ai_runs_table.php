<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('native_ai_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_practice_session_id')->nullable()->constrained('study_practice_sessions')->nullOnDelete();
            $table->string('feature_key', 64);
            $table->string('purpose', 64);
            $table->string('provider', 32);
            $table->string('model', 120);
            $table->string('capacity_tier', 24)->default('standard');
            $table->string('status', 24)->default('running');
            $table->string('request_hash', 64)->nullable();
            $table->string('provider_response_id', 160)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['feature_key', 'purpose', 'created_at']);
            $table->index(['study_practice_session_id', 'created_at']);
            $table->index('request_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_ai_runs');
    }
};
