<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('design_task_requests', 'decision_reason')) {
                $table->text('decision_reason')->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('design_task_requests', function (Blueprint $table) {
            if (Schema::hasColumn('design_task_requests', 'decision_reason')) {
                $table->dropColumn('decision_reason');
            }
        });
    }
};
