<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TestUsersSeeder extends Seeder
{
    /**
     * Créer des utilisateurs de test pour chaque rôle (étudiant, professeur, personnel).
     * Ces utilisateurs n'ont pas d'email valide et utilisent un identifiant pour se connecter.
     * Mot de passe : Acomat971
     */
    public function run(): void
    {
        $password = Hash::make('Acomat971');

        // S'assurer que les rôles existent
        $roles = ['etudiant', 'professeur', 'personnel'];
        foreach ($roles as $roleName) {
            if (!Role::where('name', $roleName)->exists()) {
                Role::create(['name' => $roleName, 'guard_name' => 'web']);
            }
        }

        // Utilisateur test Étudiant
        $etudiant = User::updateOrCreate(
            ['email' => 'etudiant.test@local.test'],
            [
                'name'              => 'Étudiant Test',
                'email'             => 'etudiant.test@local.test',
                'password'          => $password,
                'email_verified_at' => now(),
            ]
        );
        $etudiant->syncRoles(['etudiant']);

        // Utilisateur test Professeur
        $professeur = User::updateOrCreate(
            ['email' => 'prof.test@local.test'],
            [
                'name'              => 'Prof Test',
                'email'             => 'prof.test@local.test',
                'password'          => $password,
                'email_verified_at' => now(),
            ]
        );
        $professeur->syncRoles(['professeur']);

        // Utilisateur test Personnel
        $personnel = User::updateOrCreate(
            ['email' => 'personnel.test@local.test'],
            [
                'name'              => 'Personnel Test',
                'email'             => 'personnel.test@local.test',
                'password'          => $password,
                'email_verified_at' => now(),
            ]
        );
        $personnel->syncRoles(['personnel']);

        $this->command->info('✓ 3 utilisateurs de test créés avec succès :');
        $this->command->info('  - Étudiant Test (etudiant.test@local.test / Acomat971)');
        $this->command->info('  - Prof Test (prof.test@local.test / Acomat971)');
        $this->command->info('  - Personnel Test (personnel.test@local.test / Acomat971)');
        $this->command->info('');
        $this->command->info('⚠ Pour se connecter, utilisez l\'email ci-dessus comme identifiant.');
    }
}
