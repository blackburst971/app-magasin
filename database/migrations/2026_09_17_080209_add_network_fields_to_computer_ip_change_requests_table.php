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
        Schema::table('computer_ip_change_requests', function (Blueprint $table) {
            $table->string('old_subnet_mask')->nullable()->after('old_ip_addresses');
            $table->string('new_subnet_mask')->nullable()->after('new_ip_addresses');
            $table->string('old_gateway')->nullable()->after('old_subnet_mask');
            $table->string('new_gateway')->nullable()->after('new_subnet_mask');
            $table->string('old_dns_primary')->nullable()->after('old_gateway');
            $table->string('new_dns_primary')->nullable()->after('new_gateway');
            $table->string('old_dns_secondary')->nullable()->after('old_dns_primary');
            $table->string('new_dns_secondary')->nullable()->after('new_dns_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('computer_ip_change_requests', function (Blueprint $table) {
            $table->dropColumn([
                'old_subnet_mask', 'new_subnet_mask',
                'old_gateway', 'new_gateway',
                'old_dns_primary', 'new_dns_primary',
                'old_dns_secondary', 'new_dns_secondary'
            ]);
        });
    }
};
