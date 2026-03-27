<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consommable_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consommable_id')->constrained('consommables')->cascadeOnDelete();
            $table->integer('old_quantity');
            $table->integer('new_quantity');
            $table->integer('quantity_change');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['import', 'add', 'remove', 'request_approved', 'edit']);
            $table->foreignId('consommable_request_id')->nullable()->constrained('consommable_requests')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('consommable_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consommable_movements');
    }
};
