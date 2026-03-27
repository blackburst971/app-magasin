<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@lycee.fr')],
            [
                'name' => env('ADMIN_NAME', 'Administrateur'),
                'email' => env('ADMIN_EMAIL', 'admin@lycee.fr'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@1234')),
                'department' => 'Administration',
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('admin');
        $this->command->info("Admin created: {$admin->email} / " . env('ADMIN_PASSWORD', 'Admin@1234'));

        // Demo user
        $user = User::firstOrCreate(
            ['email' => 'user@lycee.fr'],
            [
                'name' => 'Utilisateur Démo',
                'email' => 'user@lycee.fr',
                'password' => Hash::make('password'),
                'department' => 'BTS SIO',
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole('user');
        $this->command->info("Demo user created: user@lycee.fr / password");
    }
}
