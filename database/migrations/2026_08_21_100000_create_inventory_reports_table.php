<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_reports', function (Blueprint $table) {
            $table->id();
            $table->date('session_date');
            $table->string('label')->nullable();              // Titre optionnel donné par l'admin
            $table->json('model_names');                      // Noms des modèles inventoriés
            $table->integer('total_count')->default(0);
            $table->integer('present_count')->default(0);
            $table->integer('missing_count')->default(0);
            $table->integer('pending_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reports');
    }
};
