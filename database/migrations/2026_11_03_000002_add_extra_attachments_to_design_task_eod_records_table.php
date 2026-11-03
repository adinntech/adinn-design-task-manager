<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress Update / Rework can carry several separate files. The first stays
 * in attachment_*; any further files are listed here as
 * [{disk, path, name}, ...] so existing rows and readers are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_eod_records', function (Blueprint $table) {
            $table->json('extra_attachments')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('design_task_eod_records', function (Blueprint $table) {
            $table->dropColumn('extra_attachments');
        });
    }
};
