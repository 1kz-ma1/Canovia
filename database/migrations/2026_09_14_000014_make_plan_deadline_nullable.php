<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->date('deadline')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Existing plans may intentionally have no deadline after this migration.
        // Keep rollback safe instead of forcing a synthetic date.
    }
};
