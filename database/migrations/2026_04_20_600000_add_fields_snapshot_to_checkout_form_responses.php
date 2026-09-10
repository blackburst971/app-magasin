<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_form_responses', function (Blueprint $table) {
            // Snapshot des champs au moment de la soumission :
            // garantit que la fiche de sortie affiche toujours les bonnes réponses
            // même si le formulaire est modifié/supprimé après coup.
            $table->json('fields_snapshot')->nullable()->after('responses');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_form_responses', function (Blueprint $table) {
            $table->dropColumn('fields_snapshot');
        });
    }
};
