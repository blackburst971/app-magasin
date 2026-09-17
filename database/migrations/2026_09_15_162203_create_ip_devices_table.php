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
        Schema::create('ip_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('device_type')->nullable(); // Type d'appareil (Imprimante, Switch, Point d'accès, etc.)
            $table->string('mac_address')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('subnet_mask')->default('255.255.255.0');
            $table->string('gateway')->nullable();
            $table->string('dns_primary')->nullable();
            $table->string('dns_secondary')->nullable();
            $table->foreignId('place_id')->nullable()->constrained('places')->nullOnDelete();
            $table->string('location')->nullable(); // Texte libre pour l'emplacement
            $table->string('brand')->nullable(); // Marque
            $table->string('model')->nullable(); // Modèle
            $table->text('notes')->nullable();
            $table->string('status')->default('active'); // active, inactive, maintenance
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_devices');
    }
};
