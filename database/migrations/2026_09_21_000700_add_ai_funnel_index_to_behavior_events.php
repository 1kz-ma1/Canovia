<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('behavior_events', function (Blueprint $table) {
            $table->index(['event_type', 'occurred_at'], 'behavior_type_time_index');
        });
    }

    public function down(): void
    {
        Schema::table('behavior_events', function (Blueprint $table) {
            $table->dropIndex('behavior_type_time_index');
        });
    }
};
