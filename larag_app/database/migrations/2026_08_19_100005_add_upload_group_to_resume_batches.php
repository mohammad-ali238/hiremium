<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queued intake: an upload now creates ONE batch immediately (status
 * 'extracting'), then a queued job expands ZIPs / validates / dedupes and splits
 * into as many batches as needed. All batches from the same upload share an
 * upload_group_id so the batches page can list its siblings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->string('upload_group_id', 36)->nullable()->after('created_by')->index();
            $table->json('intake')->nullable()->after('rejected_files'); // queued-intake payload + summary
        });
    }

    public function down(): void
    {
        Schema::table('resume_batches', function (Blueprint $table) {
            $table->dropColumn(['upload_group_id', 'intake']);
        });
    }
};
