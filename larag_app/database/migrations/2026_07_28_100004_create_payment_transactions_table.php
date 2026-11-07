<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable(); // our local subscription
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('lemonsqueezy_subscription_id')->nullable();
            $table->string('lemonsqueezy_order_id')->nullable();
            $table->string('lemonsqueezy_customer_id')->nullable();
            $table->string('event_name');
            $table->string('status')->nullable();
            $table->unsignedBigInteger('amount_total')->nullable(); // minor units (cents)
            $table->string('currency', 8)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Idempotency: a given LS event for a given order is processed once.
            $table->index(['event_name', 'lemonsqueezy_order_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
