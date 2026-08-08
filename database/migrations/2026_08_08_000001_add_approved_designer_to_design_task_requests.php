<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_requests', function (Blueprint $table) {
            $table->foreignId('approved_designer_id')
                ->nullable()
                ->after('target_designer_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('design_task_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_designer_id');
        });
    }
};
