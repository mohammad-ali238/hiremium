<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // LemonSqueezy linkage + renewal state. A subscription created by an
            // admin (manual "Assign plan") leaves these null.
            $table->string('lemonsqueezy_subscription_id')->nullable()->after('status');
            $table->string('lemonsqueezy_order_id')->nullable()->after('lemonsqueezy_subscription_id');
            $table->string('lemonsqueezy_customer_id')->nullable()->after('lemonsqueezy_order_id');
            $table->string('lemonsqueezy_status')->nullable()->after('lemonsqueezy_customer_id');
            $table->timestamp('renews_at')->nullable()->after('lemonsqueezy_status');

            $table->index('lemonsqueezy_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['lemonsqueezy_subscription_id']);
            $table->dropColumn([
                'lemonsqueezy_subscription_id',
                'lemonsqueezy_order_id',
                'lemonsqueezy_customer_id',
                'lemonsqueezy_status',
                'renews_at',
            ]);
        });
    }
};
