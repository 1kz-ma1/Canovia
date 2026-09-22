<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_packs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 180);
            $table->string('exam_code', 80)->nullable()->index();
            $table->string('subject', 120)->nullable();
            $table->string('version', 40)->default('1');
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('downloadable')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_pack_id')->constrained()->cascadeOnDelete();
            $table->string('external_key', 120)->nullable();
            $table->string('source_type', 32)->default('canovia_original')->index();
            $table->string('source_reference', 500)->nullable();
            $table->text('prompt');
            $table->json('response_schema');
            $table->json('grading_rule')->nullable();
            $table->json('learning_metadata')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['question_pack_id', 'external_key']);
            $table->index(['question_pack_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_packs');
    }
};
