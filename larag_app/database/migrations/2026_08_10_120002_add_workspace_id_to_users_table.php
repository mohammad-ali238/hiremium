<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user belongs to exactly one workspace (their own if they self-signed-up, or
 * the inviting workspace if they accepted an invite). Nullable so existing rows
 * migrate cleanly; backfilled by the ProvisionWorkspaces command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')
                ->constrained('workspaces')->nullOnDelete();
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
