<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            // Qui a rapporté cet équipement (peut être différent du réservant)
            $table->foreignId('returned_by_user_id')->nullable()->after('return_notes')->constrained('users')->nullOnDelete();
            // L'équipement est signalé manquant
            $table->boolean('is_missing')->default(false)->after('returned_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_assets', function (Blueprint $table) {
            $table->dropForeign(['returned_by_user_id']);
            $table->dropColumn(['returned_by_user_id', 'is_missing']);
        });
    }
};
