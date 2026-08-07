<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_comment_id')
                ->constrained('design_task_comments')
                ->cascadeOnDelete();
            $table->string('disk')->default('spaces');
            $table->text('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_comment_attachments');
    }
};
