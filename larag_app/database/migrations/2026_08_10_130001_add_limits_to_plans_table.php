<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan usage limits enforced across the recruiter dashboard. Defaults match
 * the Free plan so existing rows are safe until reseeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_jobs')->default(3)->after('credit_limit');
            $table->unsignedInteger('max_seats')->default(1)->after('max_jobs');
            $table->unsignedInteger('max_files_per_batch')->default(25)->after('max_seats');
            $table->unsignedInteger('max_file_mb')->default(10)->after('max_files_per_batch');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_jobs', 'max_seats', 'max_files_per_batch', 'max_file_mb']);
        });
    }
};
