<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->uuid('creation_request_id')->nullable()->after('owner_token');
            $table->unique('creation_request_id', 'plans_creation_request_unique');
        });

        Schema::table('plan_adjustments', function (Blueprint $table) {
            $table->string('request_hash', 64)->nullable()->after('flow');
            $table->unique(['plan_id', 'request_hash'], 'plan_adjustments_request_unique');
        });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->uuid('start_request_id')->nullable()->after('client_session_id');
            $table->unique('start_request_id', 'work_sessions_start_request_unique');
        });

        Schema::create('work_session_actor_locks', function (Blueprint $table) {
            $table->string('actor_token', 64)->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_session_actor_locks');

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropUnique('work_sessions_start_request_unique');
            $table->dropColumn('start_request_id');
        });

        Schema::table('plan_adjustments', function (Blueprint $table) {
            $table->dropUnique('plan_adjustments_request_unique');
            $table->dropColumn('request_hash');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropUnique('plans_creation_request_unique');
            $table->dropColumn('creation_request_id');
        });
    }
};
