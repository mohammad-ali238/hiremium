<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider_name')->nullable()->after('registration_ip');
            $table->string('provider_id')->nullable()->after('provider_name');

            $table->index(['provider_name', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider_name', 'provider_id']);
            $table->dropColumn(['provider_name', 'provider_id']);
        });
    }
};
