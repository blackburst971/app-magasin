<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();   // batiment, etage, etc.
            $table->integer('capacity')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_reservable')->default(true);
            $table->string('color', 7)->default('#8b5cf6'); // couleur calendrier (hex)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
