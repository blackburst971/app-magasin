<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_model_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_model_id')
                  ->constrained('equipment_models')
                  ->cascadeOnDelete();
            $table->string('name');           // Nom affiché (ex: "Notice utilisateur")
            $table->string('original_name');  // Nom du fichier d'origine
            $table->string('path');           // Chemin storage (model_docs/xxx.pdf)
            $table->unsignedBigInteger('size')->default(0); // Taille en octets
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_model_documents');
    }
};
