<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Permissions disponibles dans le système et leurs assignations par défaut.
     */
    private array $permissions = [
        'bypass_supervisor_approval' => [
            'roles' => ['admin', 'professeur'],
        ],
        'access_supervisor_panel' => [
            'roles' => ['admin', 'professeur'],
        ],
        'manage_inventory' => [
            'roles' => ['admin'],
        ],
        'manage_users' => [
            'roles' => ['admin'],
        ],
        'manage_reservations' => [
            'roles' => ['admin'],
        ],
        'view_reports' => [
            'roles' => ['admin'],
        ],
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Créer le rôle professeur s'il n'existe pas
        $professeurRole = Role::firstOrCreate(['name' => 'professeur', 'guard_name' => 'web']);
        $adminRole      = Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        $userRole       = Role::firstOrCreate(['name' => 'user',       'guard_name' => 'web']);

        $roles = [
            'admin'      => $adminRole,
            'professeur' => $professeurRole,
            'user'       => $userRole,
        ];

        // 2. Créer les permissions et les assigner aux bons rôles
        foreach ($this->permissions as $permName => $config) {
            $perm = Permission::firstOrCreate([
                'name'       => $permName,
                'guard_name' => 'web',
            ]);

            foreach ($config['roles'] as $roleName) {
                if (isset($roles[$roleName])) {
                    $roles[$roleName]->givePermissionTo($perm);
                }
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys($this->permissions) as $permName) {
            Permission::where('name', $permName)->delete();
        }

        Role::where('name', 'professeur')->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
