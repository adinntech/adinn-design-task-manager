<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_task_printing_file_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_task_id')->constrained('design_tasks')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->json('to_recipients');
            $table->json('cc_recipients')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->string('transfer_url')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_task_printing_file_mails');
    }
};
