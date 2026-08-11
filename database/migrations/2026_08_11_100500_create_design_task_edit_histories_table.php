<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('design_task_edit_histories')) { return; }
        Schema::create('design_task_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->foreignId('edited_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('edit_batch_id')->index();
            $table->string('field_name',190);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
            $table->index(['design_task_id','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('design_task_edit_histories'); }
};
