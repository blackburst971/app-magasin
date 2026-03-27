<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();           // slug unique: 'available', 'checked_out'…
            $table->string('label');                   // 'Disponible', 'Sorti'…
            $table->string('color')->default('gray');  // clé couleur: green, blue, red, gray…
            $table->boolean('is_system')->default(false);           // statuts système non supprimables
            $table->boolean('blocks_reservation')->default(false);  // bloque les nouvelles réservations
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed des statuts par défaut
        DB::table('asset_statuses')->insert([
            ['key' => 'available',   'label' => 'Disponible',  'color' => 'green',  'is_system' => true,  'blocks_reservation' => false, 'sort_order' => 1,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'checked_out', 'label' => 'Sorti',       'color' => 'purple', 'is_system' => true,  'blocks_reservation' => false, 'sort_order' => 2,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'reserved',    'label' => 'Réservé',     'color' => 'orange', 'is_system' => true,  'blocks_reservation' => false, 'sort_order' => 3,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'maintenance', 'label' => 'Maintenance', 'color' => 'yellow', 'is_system' => true,  'blocks_reservation' => true,  'sort_order' => 4,  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'retired',     'label' => 'Retiré',      'color' => 'gray',   'is_system' => true,  'blocks_reservation' => true,  'sort_order' => 5,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_statuses');
    }
};
