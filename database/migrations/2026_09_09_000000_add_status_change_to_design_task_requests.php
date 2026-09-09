<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE design_task_requests MODIFY request_type ENUM('decline', 'split', 'swap', 'status_change')");

        Schema::table('design_task_requests', function (Blueprint $table) {
            $table->string('from_status')->nullable()->after('reason');
            $table->string('to_status')->nullable()->after('from_status');
        });
    }

    public function down(): void
    {
        Schema::table('design_task_requests', function (Blueprint $table) {
            $table->dropColumn(['from_status', 'to_status']);
        });

        DB::statement("ALTER TABLE design_task_requests MODIFY request_type ENUM('decline', 'split', 'swap')");
    }
};
