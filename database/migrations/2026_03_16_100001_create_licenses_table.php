<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // nom du logiciel
            $table->string('version')->nullable();
            $table->string('manufacturer')->nullable();      // éditeur
            $table->text('license_key')->nullable();         // clé de licence
            $table->unsignedInteger('seats')->nullable();    // null = illimité
            $table->enum('type', [
                'per_seat',    // par poste
                'volume',      // licence en volume (académique)
                'oem',         // liée au matériel
                'cloud',       // SaaS / en ligne
                'dongle',      // clé physique USB
                'open_source', // logiciel libre
            ])->default('per_seat');
            $table->boolean('is_cloud')->default(false);
            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_cost', 10, 2)->nullable();
            $table->string('order_number')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
