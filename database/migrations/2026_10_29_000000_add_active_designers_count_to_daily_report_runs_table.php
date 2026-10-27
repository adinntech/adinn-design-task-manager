<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Daily Reporting: designers with at least one In Progress / Rework task on the report day. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_report_runs', function (Blueprint $table) {
            $table->unsignedInteger('active_designers_count')->default(0)->after('designers_evaluated');
        });
    }

    public function down(): void
    {
        Schema::table('daily_report_runs', function (Blueprint $table) {
            $table->dropColumn('active_designers_count');
        });
    }
};
