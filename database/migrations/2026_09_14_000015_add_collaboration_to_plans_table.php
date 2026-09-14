<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_collaborative')->default(false)->after('is_public');
            $table->string('collaboration_join_code', 16)->nullable()->unique()->after('is_collaborative');
            $table->string('collaboration_share_token', 80)->nullable()->unique()->after('collaboration_join_code');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique(['collaboration_join_code']);
            $table->dropUnique(['collaboration_share_token']);
            $table->dropColumn(['is_collaborative', 'collaboration_join_code', 'collaboration_share_token']);
        });
    }
};
