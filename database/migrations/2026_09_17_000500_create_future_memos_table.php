<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('future_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->char('guest_token_hash', 64)->nullable()->index();
            $table->string('kind', 32);
            $table->string('category', 32)->nullable();
            $table->text('content');
            $table->boolean('use_for_ai')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'use_for_ai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('future_memos');
    }
};
