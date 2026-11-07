<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchasable credit packs that add to a workspace's persistent pack balance.
 * Checkout wiring (LemonSqueezy) lands with billing; this defines the catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_up_packs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('credits');
            $table->decimal('price', 8, 2)->default(0);
            $table->string('lemonsqueezy_variant_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_packs');
    }
};
