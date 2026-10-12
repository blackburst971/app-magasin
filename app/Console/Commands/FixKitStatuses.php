<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;

class FixKitStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-kit-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige les statuts des kits pour les réservations approuvées et actives';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Recherche des réservations avec kits...');

        // Réservations approuvées (kits devraient être "reserved")
        $approvedReservations = Reservation::where('status', 'approved')
            ->whereHas('kits')
            ->with('kits')
            ->get();

        $this->info("📦 {$approvedReservations->count()} réservation(s) approuvée(s) avec kits trouvée(s).");

        foreach ($approvedReservations as $reservation) {
            $this->line("  → Réservation #{$reservation->id} : Mise à jour des kits en statut 'reserved'");
            $reservation->updateKitStatuses('reserved');
        }

        // Réservations actives (kits devraient être "checked_out")
        $activeReservations = Reservation::where('status', 'active')
            ->whereHas('kits')
            ->with('kits')
            ->get();

        $this->info("📦 {$activeReservations->count()} réservation(s) active(s) avec kits trouvée(s).");

        foreach ($activeReservations as $reservation) {
            $this->line("  → Réservation #{$reservation->id} : Mise à jour des kits en statut 'checked_out'");
            $reservation->updateKitStatuses('checked_out');
        }

        $this->newLine();
        $this->info('✅ Statuts des kits mis à jour avec succès !');

        return 0;
    }
}
