<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->string('zoho_project_number', 60)->nullable()->after('task_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropIndex(['zoho_project_number']);
            $table->dropColumn('zoho_project_number');
        });
    }
};
