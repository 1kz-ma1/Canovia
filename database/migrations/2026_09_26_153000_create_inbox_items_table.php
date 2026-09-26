<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_token', 191)->nullable()->index();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_type', 24);
            $table->string('status', 24)->default('new');
            $table->string('title', 255)->nullable();
            $table->longText('content')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['actor_token', 'status', 'created_at']);
            $table->index(['plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_items');
    }
};
