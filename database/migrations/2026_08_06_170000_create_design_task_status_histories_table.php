<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->string('change_source')->default('designer');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['design_task_id', 'created_at'], 'design_task_status_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_status_histories');
    }
};
