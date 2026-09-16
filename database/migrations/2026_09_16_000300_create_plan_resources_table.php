<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('resource_type', 16)->default('file');
            $table->string('title');
            $table->text('url');
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'provider']);
        });

        Schema::create('plan_resource_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_resource_id')->constrained('plan_resources')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_resource_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_resource_task');
        Schema::dropIfExists('plan_resources');
    }
};
