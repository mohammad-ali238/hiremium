<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Yearly billing interval for the same tier (annual bundle of credits).
            $table->decimal('yearly_price', 10, 2)->nullable()->after('price');
            $table->unsignedInteger('yearly_credit_limit')->nullable()->after('credit_limit');
            $table->string('lemonsqueezy_yearly_variant_id')->nullable()->after('lemonsqueezy_variant_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Which interval this subscription was bought on (monthly | yearly).
            $table->string('billing_interval')->default('monthly')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['yearly_price', 'yearly_credit_limit', 'lemonsqueezy_yearly_variant_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_interval');
        });
    }
};
