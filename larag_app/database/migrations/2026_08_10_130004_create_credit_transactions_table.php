<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only credit ledger: every debit (negative) and credit (positive),
 * tagged with the source it moved (monthly subscription vs top-up pack).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20);   // 'monthly' | 'pack'
            $table->integer('amount');       // negative = spent, positive = granted/refunded
            $table->string('reason', 100)->nullable();
            $table->nullableMorphs('subject');
            $table->timestamp('created_at')->nullable();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
