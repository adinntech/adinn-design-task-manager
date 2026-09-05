<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_bd_reviews', function (Blueprint $table) {
            $table->string('attachment_disk')->nullable()->after('comment');
            $table->text('attachment_path')->nullable()->after('attachment_disk');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('design_task_bd_reviews', function (Blueprint $table) {
            $table->dropColumn(['attachment_disk', 'attachment_path', 'attachment_original_name', 'attachment_size']);
        });
    }
};
