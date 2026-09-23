<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_practice_sessions', function (Blueprint $table) {
            $table->string('exercise_title', 120)->nullable()->after('status');
            $table->json('questions_snapshot')->nullable()->after('selected_questions');
            $table->json('draft_answers')->nullable()->after('questions_snapshot');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_answers')->index();
        });
    }

    public function down(): void
    {
        Schema::table('study_practice_sessions', function (Blueprint $table) {
            $table->dropIndex(['draft_saved_at']);
            $table->dropColumn([
                'exercise_title',
                'questions_snapshot',
                'draft_answers',
                'draft_saved_at',
            ]);
        });
    }
};
