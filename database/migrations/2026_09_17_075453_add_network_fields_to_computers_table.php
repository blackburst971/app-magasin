<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->string('mac_address')->nullable()->after('ip_address');
            $table->string('subnet_mask')->nullable()->default('255.255.255.0')->after('mac_address');
            $table->string('gateway')->nullable()->after('subnet_mask');
            $table->string('dns_primary')->nullable()->after('gateway');
            $table->string('dns_secondary')->nullable()->after('dns_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropColumn(['mac_address', 'subnet_mask', 'gateway', 'dns_primary', 'dns_secondary']);
        });
    }
};
