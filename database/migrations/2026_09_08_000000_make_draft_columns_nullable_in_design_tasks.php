<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original table never declared an explicit default for the
        // due_at timestamp; under this MySQL build (timestamp server mode,
        // explicit_defaults_for_timestamp=0) the second TIMESTAMP column was
        // given an implicit zero-date default ('0000-00-00 00:00:00'). Under
        // SQL strict mode that stale default makes EVERY table rebuild fail
        // with "Invalid default value for 'due_at'". Clear it explicitly
        // first, then the nullable change below can run safely.
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->default(null)->change();
        });

        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropForeign(['designer_id']);
            $table->foreignId('designer_id')->nullable()->change();
            $table->foreign('designer_id')->references('id')->on('users');
            $table->unsignedInteger('total_creatives')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropForeign(['designer_id']);
            $table->foreignId('designer_id')->nullable(false)->change();
            $table->foreign('designer_id')->references('id')->on('users');
            $table->timestamp('due_at')->nullable(false)->default('2026-01-01 00:00:00')->change();
            $table->unsignedInteger('total_creatives')->nullable(false)->change();
        });
    }
};
