<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Informatique', 'description' => 'Ordinateurs, tablettes, périphériques', 'color' => '#3B82F6'],
            ['name' => 'Audio/Vidéo', 'description' => 'Caméras, micros, projecteurs, enceintes', 'color' => '#8B5CF6'],
            ['name' => 'Réseau', 'description' => 'Switches, câbles, équipements réseau', 'color' => '#10B981'],
            ['name' => 'Impression', 'description' => 'Imprimantes, scanners, consommables', 'color' => '#F59E0B'],
            ['name' => 'Outillage', 'description' => 'Outils divers, matériel d\'atelier', 'color' => '#EF4444'],
            ['name' => 'Divers', 'description' => 'Matériel non catégorisé', 'color' => '#6B7280'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['name' => $category['name']], $category);
        }

        $this->command->info('Categories created: ' . count($categories));
    }
}
