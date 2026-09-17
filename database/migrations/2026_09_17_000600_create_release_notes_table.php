<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->nullable()->unique()->constrained('feedbacks')->nullOnDelete();
            $table->string('version', 32)->index();
            $table->string('title', 180);
            $table->text('summary');
            $table->text('user_voice')->nullable();
            $table->json('highlights');
            $table->text('tip')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_notes');
    }
};
