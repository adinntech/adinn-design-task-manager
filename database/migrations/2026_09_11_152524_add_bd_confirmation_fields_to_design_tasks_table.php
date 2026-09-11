<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            // Null for every BD-created task; set on a Designer-created task
            // once it's submitted (pending) and again once the selected BD
            // decides (approved/rejected) — see App\Services\DesignTaskStatusService
            // for the two new statuses (pending_bd_approval, bd_rejected) this backs.
            $table->string('bd_approval_status')->nullable()->after('requirements');
            $table->text('bd_approval_comment')->nullable()->after('bd_approval_status');
            $table->foreignId('bd_decided_by')->nullable()->after('bd_approval_comment')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('bd_decided_at')->nullable()->after('bd_decided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('design_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bd_decided_by');
            $table->dropColumn(['bd_approval_status', 'bd_approval_comment', 'bd_decided_at']);
        });
    }
};
