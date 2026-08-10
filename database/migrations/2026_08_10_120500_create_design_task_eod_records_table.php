<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_eod_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained('users');
            $table->unsignedInteger('completed_count');
            $table->unsignedInteger('total_creatives_snapshot');
            $table->unsignedInteger('cumulative_completed');
            $table->unsignedInteger('remaining_creatives');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['design_task_id', 'submitted_at']);
            $table->index(['designer_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_eod_records');
    }
};
