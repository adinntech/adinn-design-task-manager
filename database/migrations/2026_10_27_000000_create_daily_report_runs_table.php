<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per report date — the unique report_date is the idempotency key that
 * stops the nightly Daily Reporting mail from being delivered twice.
 * Purely additive; no existing table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_report_runs', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->string('idempotency_key', 80)->unique();
            $table->string('timezone', 40);
            $table->string('status', 20)->default('pending'); // pending | sending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->unsignedInteger('designers_evaluated')->default(0);
            $table->unsignedInteger('submitters_count')->default(0);
            $table->unsignedInteger('included_count')->default(0);
            $table->unsignedInteger('no_tasks_count')->default(0);
            $table->unsignedInteger('all_completed_count')->default(0);
            $table->unsignedInteger('active_no_progress_count')->default(0);
            $table->unsignedInteger('awaiting_review_count')->default(0);

            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->json('report_snapshot')->nullable();

            $table->unsignedSmallInteger('api_status_code')->nullable();
            $table->text('api_message')->nullable();

            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_runs');
    }
};
