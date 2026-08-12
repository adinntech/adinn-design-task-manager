<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_eod_records', function (Blueprint $table) {
            $table->string('update_type', 30)->default('progress')->after('designer_id');
            $table->unsignedInteger('rework_count_snapshot')->nullable()->after('remaining_creatives');
            $table->string('attachment_disk', 30)->nullable()->after('rework_count_snapshot');
            $table->text('attachment_path')->nullable()->after('attachment_disk');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('design_task_eod_records', function (Blueprint $table) {
            $table->dropColumn([
                'update_type',
                'rework_count_snapshot',
                'attachment_disk',
                'attachment_path',
                'attachment_original_name',
            ]);
        });
    }
};
