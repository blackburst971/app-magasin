<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('name');                         // Nom affiché
            $table->string('path');                         // Chemin storage/public
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // Taille en octets
            $table->string('type')->default('document');    // 'image' | 'document'
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_files');
    }
};
