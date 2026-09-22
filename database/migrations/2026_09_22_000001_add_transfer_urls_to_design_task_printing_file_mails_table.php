<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_task_printing_file_mails', function (Blueprint $table) {
            // Multi-link support — transfer_url (single string) stays as-is for
            // backward compatibility with existing rows/readers; new sends also
            // populate this JSON column with the full list of links.
            $table->json('transfer_urls')->nullable()->after('transfer_url');
        });
    }

    public function down(): void
    {
        Schema::table('design_task_printing_file_mails', function (Blueprint $table) {
            $table->dropColumn('transfer_urls');
        });
    }
};
