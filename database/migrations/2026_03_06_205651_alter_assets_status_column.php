<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite ne supporte pas ENUM, la colonne est déjà en TEXT.
        // MySQL: convertir ENUM → VARCHAR(50)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE assets MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'available'");
        }
        // Pour SQLite: pas de changement nécessaire (déjà TEXT)
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE assets MODIFY COLUMN status ENUM('available','reserved','checked_out','maintenance','retired') NOT NULL DEFAULT 'available'");
        }
    }
};
