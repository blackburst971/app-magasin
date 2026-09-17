<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            // Supprimer l'ancien champ location (texte libre)
            $table->dropColumn('location');
        });

        Schema::table('computers', function (Blueprint $table) {
            // Ajouter place_id qui pointe vers la table places
            $table->foreignId('place_id')->nullable()->after('asset_id')->constrained('places')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('computers', function (Blueprint $table) {
            $table->dropForeign(['place_id']);
            $table->dropColumn('place_id');
        });

        Schema::table('computers', function (Blueprint $table) {
            $table->string('location')->nullable()->after('name');
        });
    }
};
