<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('all_users_mail', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mail');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('all_users_mail');
    }
};
