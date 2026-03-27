<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesController extends Controller
{
    /**
     * Métadonnées des permissions (label + description pour l'UI).
     */
    private array $permissionMeta = [
        'bypass_supervisor_approval' => [
            'label'       => 'Pas de validation superviseur',
            'description' => 'Les réservations de ce rôle vont directement à l\'admin sans passer par un responsable.',
            'locked_for'  => ['admin'], // Toujours ON pour admin, non modifiable
        ],
        'access_supervisor_panel' => [
            'label'       => 'Espace superviseur',
            'description' => 'Accès à l\'espace de validation des demandes des supervisés.',
            'locked_for'  => ['admin'],
        ],
        'manage_inventory' => [
            'label'       => 'Gérer l\'inventaire',
            'description' => 'Créer, modifier et supprimer des équipements, catégories, kits et fabricants.',
            'locked_for'  => ['admin'],
        ],
        'manage_users' => [
            'label'       => 'Gérer les utilisateurs',
            'description' => 'Créer, modifier et supprimer des comptes utilisateurs.',
            'locked_for'  => ['admin'],
        ],
        'manage_reservations' => [
            'label'       => 'Gérer les réservations',
            'description' => 'Approuver, rejeter, modifier et gérer toutes les réservations.',
            'locked_for'  => ['admin'],
        ],
        'view_reports' => [
            'label'       => 'Accès aux rapports',
            'description' => 'Consulter les rapports et statistiques d\'utilisation.',
            'locked_for'  => ['admin'],
        ],
    ];

    public function index()
    {
        $roles = Role::with('permissions')->get();

        // Construire la matrice : pour chaque rôle, quelles permissions il a
        $matrix = $roles->mapWithKeys(fn($role) => [
            $role->name => $role->permissions->pluck('name')->toArray(),
        ]);

        // Permissions avec leurs métadonnées
        $permissions = collect($this->permissionMeta)->map(fn($meta, $name) => [
            'name'        => $name,
            'label'       => $meta['label'],
            'description' => $meta['description'],
            'locked_for'  => $meta['locked_for'],
        ])->values();

        return Inertia::render('Admin/Roles', [
            'roles'       => $roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
            'permissions' => $permissions,
            'matrix'      => $matrix,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'role'        => 'required|string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // On ne peut pas modifier les permissions du rôle admin
        if ($data['role'] === 'admin') {
            return back()->withErrors(['role' => 'Les permissions du rôle admin ne peuvent pas être modifiées.']);
        }

        $role = Role::findByName($data['role']);

        // Filtrer : exclure les permissions verrouillées pour admin (elles ne concernent pas ce contexte)
        // et s'assurer qu'on ne peut pas attribuer des permissions "locked_for admin" si admin est le rôle
        $newPerms = collect($data['permissions'] ?? []);

        $role->syncPermissions($newPerms->toArray());

        // Vider le cache Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return back()->with('success', 'Permissions du rôle "' . $data['role'] . '" mises à jour.');
    }
}
