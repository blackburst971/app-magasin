<?php

namespace App\Console\Commands;

use App\Models\Consommable;
use App\Models\User;
use App\Notifications\ConsommableLowStockNotification;
use Illuminate\Console\Command;

class CheckLowStock extends Command
{
    protected $signature   = 'app:check-low-stock';
    protected $description = 'Vérifie les consommables en stock bas et notifie les administrateurs.';

    public function handle(): int
    {
        // Vérifier si les alertes de stock bas sont activées
        if (\App\Models\Setting::get('alert_low_stock_enabled', 'true') !== 'true') {
            $this->info('Alertes de stock bas désactivées dans les paramètres.');
            return Command::SUCCESS;
        }

        // Consommables en stock bas dont l'alerte n'a jamais été envoyée (une seule fois)
        $lowStock = Consommable::where('min_stock', '>', 0)
            ->whereRaw('quantity <= min_stock')
            ->whereNull('low_stock_alerted_at')
            ->get();

        if ($lowStock->isEmpty()) {
            $this->info('Aucun consommable en stock bas.');
            return Command::SUCCESS;
        }

        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('Aucun administrateur trouvé pour les notifications.');
            return Command::SUCCESS;
        }

        $sent = 0;
        foreach ($lowStock as $consommable) {
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new ConsommableLowStockNotification($consommable));
                } catch (\Exception $e) {
                    $this->warn("Erreur notification pour {$consommable->name}: {$e->getMessage()}");
                }
            }

            $consommable->update(['low_stock_alerted_at' => now()]);
            $sent++;
        }

        $this->info("Alertes envoyées pour {$sent} consommable(s) en stock bas.");
        return Command::SUCCESS;
    }
}
