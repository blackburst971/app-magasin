<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Créer le rôle "personnel" avec les mêmes droits que "user" (aucune permission par défaut)
        Role::firstOrCreate(['name' => 'personnel', 'guard_name' => 'web']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::where('name', 'personnel')->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
