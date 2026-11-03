<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user "Web Push Notifications" ON/OFF switch (My Profile). Defaults to
 * ON so existing subscribers keep getting alerts. OFF keeps the user's
 * push_subscriptions rows; WebPushService just stops sending to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('web_push_enabled')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('web_push_enabled');
        });
    }
};
