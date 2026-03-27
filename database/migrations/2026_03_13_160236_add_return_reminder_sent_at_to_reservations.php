<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Timestamp horodatant l'envoi du rappel de retour (null = pas encore envoyé)
            $table->timestamp('return_reminder_sent_at')->nullable()->after('return_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('return_reminder_sent_at');
        });
    }
};
