<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            // available = complet et réservable
            // partial   = éléments manquants mais admin a autorisé la réservation
            // unavailable = ne peut pas être réservé
            $table->enum('status', ['available', 'partial', 'unavailable'])->default('available');
            $table->boolean('is_reservable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kits');
    }
};
