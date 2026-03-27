<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Référence au kit si la réservation porte sur un kit
            $table->foreignId('kit_id')->nullable()->after('user_id')->constrained('kits')->nullOnDelete();
            // Utilisateur qui a physiquement rapporté le matériel (peut être différent du réservant)
            $table->foreignId('returned_by_user_id')->nullable()->after('return_at')->constrained('users')->nullOnDelete();
            // Flag : le retour a des éléments manquants en attente de régularisation
            $table->boolean('has_missing_items')->default(false)->after('returned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['kit_id']);
            $table->dropForeign(['returned_by_user_id']);
            $table->dropColumn(['kit_id', 'returned_by_user_id', 'has_missing_items']);
        });
    }
};
