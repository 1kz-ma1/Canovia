<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('company_name', 255);
            $table->string('company_website', 2048)->nullable();
            $table->string('role_title', 255)->nullable();
            $table->string('stage', 32)->default('candidate')->index();
            $table->string('status', 24)->default('active')->index();
            $table->string('source', 32)->nullable();
            $table->date('applied_at')->nullable();
            $table->timestamp('next_event_at')->nullable()->index();
            $table->string('result', 32)->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'stage'], 'career_application_plan_stage_idx');
        });

        Schema::create('career_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_application_id')->nullable()->constrained('career_applications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 64)->nullable()->index();
            $table->string('source_type', 24)->index();
            $table->string('status', 24)->default('pending')->index();
            $table->string('source_url', 2048)->nullable();
            $table->string('screenshot_path', 1024)->nullable();
            $table->string('screenshot_mime', 128)->nullable();
            $table->string('screenshot_original_name', 255)->nullable();
            $table->text('raw_text')->nullable();
            $table->json('extracted_data')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['plan_id', 'status', 'captured_at'], 'career_capture_plan_status_idx');
        });

        Schema::create('career_capture_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_capture_id')->unique()->constrained('career_captures')->cascadeOnDelete();
            $table->mediumText('screenshot_data')->nullable();
            $table->unsignedInteger('byte_size')->default(0);
            $table->timestamps();
        });

        Schema::create('career_selection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_application_id')->constrained('career_applications')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_capture_id')->nullable()->constrained('career_captures')->nullOnDelete();
            $table->string('type', 32)->default('interview')->index();
            $table->string('stage', 32)->default('interview')->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->string('result', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['career_application_id', 'status', 'scheduled_at'], 'career_event_application_status_idx');
        });

        Schema::create('interview_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_application_id')->constrained('career_applications')->cascadeOnDelete();
            $table->foreignId('career_selection_event_id')->unique()->constrained('career_selection_events')->cascadeOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->json('context_snapshot')->nullable();
            $table->json('insights')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('interview_review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_review_id')->constrained('interview_reviews')->cascadeOnDelete();
            $table->string('question_key', 64);
            $table->text('prompt');
            $table->text('answer')->nullable();
            $table->string('source', 24)->default('rule');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['interview_review_id', 'question_key'], 'interview_review_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_review_answers');
        Schema::dropIfExists('interview_reviews');
        Schema::dropIfExists('career_selection_events');
        Schema::dropIfExists('career_capture_payloads');
        Schema::dropIfExists('career_captures');
        Schema::dropIfExists('career_applications');
    }
};
