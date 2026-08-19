<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_bd_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('action', 20); // rework | completed
            $table->unsignedInteger('number_of_creatives')->nullable();
            $table->text('comment')->nullable();
            $table->decimal('designer_attitude', 2, 1)->nullable();
            $table->decimal('design_satisfaction', 2, 1)->nullable();
            $table->decimal('rework_iteration', 2, 1)->nullable();
            $table->decimal('meeting_deadline', 2, 1)->nullable();
            $table->decimal('client_satisfaction', 2, 1)->nullable();
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->timestamps();

            $table->index(['design_task_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_bd_reviews');
    }
};
