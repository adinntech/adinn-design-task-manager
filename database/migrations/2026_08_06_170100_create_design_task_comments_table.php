<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status_at_comment');
            $table->text('comment');
            $table->timestamps();

            $table->index(['design_task_id', 'created_at'], 'design_task_comments_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_comments');
    }
};
