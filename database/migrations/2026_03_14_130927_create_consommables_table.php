<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consommables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference')->unique();
            $table->string('unit')->default('pièce');
            $table->integer('quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->boolean('is_requestable')->default(false);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index('is_requestable');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consommables');
    }
};
