<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#6366f1');
            $table->boolean('is_storage')->default(false);
            $table->timestamps();
        });

        // Lieux de stockage par défaut
        DB::table('places')->insert([
            ['name' => 'Le magasin',          'description' => 'Lieu de stockage principal',     'color' => '#6366f1', 'is_storage' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Le magasin général',   'description' => 'Lieu de stockage secondaire',    'color' => '#8b5cf6', 'is_storage' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
