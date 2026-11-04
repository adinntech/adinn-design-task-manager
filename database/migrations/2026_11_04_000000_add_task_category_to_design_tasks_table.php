<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            // 'client' (every existing task) or 'in_house' (BD-raised internal task).
            $table->string('task_category', 20)->default('client')->after('task_nature');
        });
    }

    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropColumn('task_category');
        });
    }
};
