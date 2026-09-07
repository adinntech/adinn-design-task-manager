<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope')->default('tasks');
            $table->timestamp('flagged_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_flags');
    }
};
