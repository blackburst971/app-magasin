<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->enum('type', ['materiel', 'consommable', 'logiciel', 'autre'])->default('materiel');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit', 50)->default('unité');
            $table->string('reference', 255)->nullable();
            $table->string('manufacturer', 255)->nullable();
            $table->string('supplier', 255)->nullable();
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('product_url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['besoin', 'en_commande', 'commande', 'livre', 'annule'])->default('besoin');
            $table->enum('priority', ['haute', 'normale', 'basse'])->default('normale');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('procurement_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name', 255);
            $table->string('description', 255)->nullable();
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_files');
        Schema::dropIfExists('procurement_items');
    }
};
