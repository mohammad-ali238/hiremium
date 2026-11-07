<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-day fingerprints of the data a run was built from. Re-submitting
        // the same CV / job under a different email is allowed only while the
        // email that first used it today still has quota left — these rows are
        // how that original identity is found.
        Schema::create('aitools_data_hashes', function (Blueprint $table) {
            $table->id();
            $table->string('tool', 64)->index();
            $table->string('hash_type', 16);                 // file|payload|combo
            $table->char('hash', 64);
            $table->date('used_date');
            $table->string('email_canonical', 190);
            $table->string('quota_email', 190);
            $table->foreignId('aitools_run_id')->nullable()->constrained('aitools_runs')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            // Lookup: "was this data used today, and by whom first?"
            $table->index(['tool', 'hash_type', 'hash', 'used_date'], 'aitools_hash_lookup_idx');
            $table->unique(['tool', 'hash_type', 'hash', 'used_date', 'email_canonical'], 'aitools_hash_identity_unq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aitools_data_hashes');
    }
};
