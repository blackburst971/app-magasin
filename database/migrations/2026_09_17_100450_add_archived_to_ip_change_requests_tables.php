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
        // Ajouter colonne archived aux demandes de changement IP pour Computers
        Schema::table('computer_ip_change_requests', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('review_notes');
            $table->timestamp('archived_at')->nullable()->after('archived');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
        });

        // Ajouter colonne archived aux demandes de changement IP pour IP Devices
        Schema::table('ip_device_ip_change_requests', function (Blueprint $table) {
            $table->boolean('archived')->default(false)->after('review_notes');
            $table->timestamp('archived_at')->nullable()->after('archived');
            $table->unsignedBigInteger('archived_by')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('computer_ip_change_requests', function (Blueprint $table) {
            $table->dropColumn(['archived', 'archived_at', 'archived_by']);
        });

        Schema::table('ip_device_ip_change_requests', function (Blueprint $table) {
            $table->dropColumn(['archived', 'archived_at', 'archived_by']);
        });
    }
};
