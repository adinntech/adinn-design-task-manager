<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `status_at_comment` records the task's actual status at comment time
     * (audit value) and was being reused to decide which UI tab a comment
     * belongs in — wrongly conflating "task was in Clarification Needed" with
     * "this comment was submitted from the Clarification form". `context` is
     * the real, source-based field: which form the comment came from.
     */
    public function up(): void
    {
        Schema::table('design_task_comments', function (Blueprint $table) {
            $table->string('context')->default('comment')->after('status_at_comment');
        });

        // Backfill: every existing clarification-sourced comment was always
        // force-stamped status_at_comment = 'need_clarification' at creation
        // (see the old persistComment()/addComment() call sites), regardless
        // of the task's live status — so this column reliably identifies true
        // historical clarification comments. Everything else defaults
        // 'comment' already and needs no backfill.
        DB::table('design_task_comments')
            ->where('status_at_comment', 'need_clarification')
            ->update(['context' => 'clarification']);
    }

    public function down(): void
    {
        Schema::table('design_task_comments', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
