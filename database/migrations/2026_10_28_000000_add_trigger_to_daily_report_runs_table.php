<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin "Send Mail" (manual) runs live alongside the nightly scheduled run, so
 * report_date can no longer be unique. The scheduled run stays one-per-date
 * through its deterministic, still-unique idempotency_key
 * ("daily-designer-progress-{date}"); each manual send gets its own key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_report_runs', function (Blueprint $table) {
            $table->string('trigger', 20)->default('scheduled')->after('report_date');
            $table->unsignedBigInteger('triggered_by')->nullable()->after('trigger');
            $table->index(['report_date', 'trigger']);
        });

        Schema::table('daily_report_runs', function (Blueprint $table) {
            $table->dropUnique(['report_date']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_report_runs', function (Blueprint $table) {
            $table->dropIndex(['report_date', 'trigger']);
            $table->dropColumn(['trigger', 'triggered_by']);
            $table->unique('report_date');
        });
    }
};
