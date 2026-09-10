<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN — recreate the table with expanded CHECK constraint.
        DB::statement('PRAGMA foreign_keys = OFF;');

        DB::statement("CREATE TABLE asset_movements_new (
            id integer not null primary key autoincrement,
            asset_id integer not null references assets(id) on delete cascade,
            reservation_id integer references reservations(id) on delete set null,
            user_id integer references users(id) on delete set null,
            action varchar not null check (action in (
                'checkout', 'return', 'maintenance', 'import', 'status_change',
                'place_assigned', 'place_returned'
            )),
            notes text,
            created_at datetime,
            updated_at datetime
        )");

        DB::statement("INSERT INTO asset_movements_new SELECT * FROM asset_movements");
        DB::statement("DROP TABLE asset_movements");
        DB::statement("ALTER TABLE asset_movements_new RENAME TO asset_movements");

        DB::statement('PRAGMA foreign_keys = ON;');
    }

    public function down(): void
    {
        // Remove 'place_assigned' and 'place_returned' from the constraint.
        DB::statement('PRAGMA foreign_keys = OFF;');

        DB::statement("CREATE TABLE asset_movements_new (
            id integer not null primary key autoincrement,
            asset_id integer not null references assets(id) on delete cascade,
            reservation_id integer references reservations(id) on delete set null,
            user_id integer references users(id) on delete set null,
            action varchar not null check (action in (
                'checkout', 'return', 'maintenance', 'import', 'status_change'
            )),
            notes text,
            created_at datetime,
            updated_at datetime
        )");

        // Only copy rows with valid actions for the old constraint
        DB::statement("INSERT INTO asset_movements_new
            SELECT * FROM asset_movements
            WHERE action IN ('checkout','return','maintenance','import','status_change')");

        DB::statement("DROP TABLE asset_movements");
        DB::statement("ALTER TABLE asset_movements_new RENAME TO asset_movements");

        DB::statement('PRAGMA foreign_keys = ON;');
    }
};
