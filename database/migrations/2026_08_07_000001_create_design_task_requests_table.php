<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained()->cascadeOnDelete();
            $table->enum('request_type', ['decline', 'split', 'swap']);
            $table->foreignId('requested_by')->constrained('users');

            $table->string('designer_head_status')->default('pending');
            $table->foreignId('designer_head_action_by')->nullable()->constrained('users');
            $table->timestamp('designer_head_action_at')->nullable();

            $table->string('admin_status')->default('pending');
            $table->foreignId('admin_action_by')->nullable()->constrained('users');
            $table->timestamp('admin_action_at')->nullable();

            $table->string('overall_status')->default('pending_designer_head');
            $table->text('reason');
            $table->foreignId('target_designer_id')->nullable()->constrained('users');
            $table->json('split_details')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_requests');
    }
};
