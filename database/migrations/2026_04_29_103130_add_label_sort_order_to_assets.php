<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Ordre personnalisé dans la page Étiquettes (null = pas encore défini)
            $table->unsignedInteger('label_sort_order')->nullable()->after('label_flagged_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('label_sort_order');
        });
    }
};
