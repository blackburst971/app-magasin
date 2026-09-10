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
        Schema::table('inventory_reports', function (Blueprint $table) {
            // Stocke les statuts précédents des équipements pour permettre l'annulation
            $table->json('previous_statuses')->nullable()->after('notes');
            // Marque si le rapport est un brouillon (en cours) ou finalisé
            $table->boolean('is_draft')->default(true)->after('previous_statuses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_reports', function (Blueprint $table) {
            $table->dropColumn(['previous_statuses', 'is_draft']);
        });
    }
};
