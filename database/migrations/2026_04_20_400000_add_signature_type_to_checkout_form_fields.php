<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SQLite ne supporte pas ALTER COLUMN pour modifier un CHECK.
     * On recrée la contrainte en reconstruisant la table via une migration de données.
     * Sur SQLite, l'approche la plus fiable est de passer la colonne en string
     * (suppression du CHECK) via une table temporaire.
     */
    public function up(): void
    {
        // SQLite ne permet pas de modifier une contrainte CHECK directement.
        // On recrée la table checkout_form_fields sans la contrainte enum restrictive.
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::statement('
            CREATE TABLE checkout_form_fields_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                checkout_form_id INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT "text",
                label VARCHAR(255) NOT NULL,
                placeholder VARCHAR(255) NULL,
                options TEXT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                "order" INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                FOREIGN KEY (checkout_form_id) REFERENCES checkout_forms(id) ON DELETE CASCADE
            )
        ');

        DB::statement('
            INSERT INTO checkout_form_fields_new
                (id, checkout_form_id, type, label, placeholder, options, required, "order", created_at, updated_at)
            SELECT id, checkout_form_id, type, label, placeholder, options, required, "order", created_at, updated_at
            FROM checkout_form_fields
        ');

        DB::statement('DROP TABLE checkout_form_fields');
        DB::statement('ALTER TABLE checkout_form_fields_new RENAME TO checkout_form_fields');

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        // Non réversible sans perte (les valeurs 'signature' seraient invalides dans l'ancien enum)
    }
};
