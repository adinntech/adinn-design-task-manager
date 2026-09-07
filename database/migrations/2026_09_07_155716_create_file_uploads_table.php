<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->string('purpose'); // progress_update | rework
            $table->string('original_filename');
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime_type')->nullable();
            $table->string('cloud_disk')->default('spaces');
            $table->string('cloud_key');
            $table->string('multipart_upload_id');
            $table->unsignedInteger('part_size_bytes');
            $table->unsignedBigInteger('uploaded_bytes')->default(0);
            $table->string('status')->default('uploading'); // uploading | completed | failed | abandoned
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'design_task_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
