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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 40);
            $table->string('category', 40)->nullable();
            $table->text('content');
            $table->boolean('use_for_ai')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'kind']);
            $table->index(['user_id', 'use_for_ai']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('future_memo_hint_snoozed_until')->nullable()->after('last_resource_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('future_memo_hint_snoozed_until');
        });

        Schema::dropIfExists('future_memos');
    }
};
