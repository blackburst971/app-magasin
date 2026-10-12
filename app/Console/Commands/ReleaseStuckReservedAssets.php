<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\AssetMovement;

class ReleaseStuckReservedAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:release-stuck-reserved-assets {--dry-run : Afficher les équipements sans les modifier} {--force : Libérer sans confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Libère les équipements bloqués en statut "reserved" qui devraient être "available"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Mode test : aucune modification ne sera appliquée');
        }

        // Récupérer tous les équipements en statut "reserved"
        $reservedAssets = Asset::where('status', 'reserved')->get();

        if ($reservedAssets->isEmpty()) {
            $this->info('✅ Aucun équipement en statut "reserved" — système propre.');
            return 0;
        }

        $this->info("📦 {$reservedAssets->count()} équipement(s) en statut 'reserved' trouvé(s).");
        $this->newLine();

        $toRelease = [];

        foreach ($reservedAssets as $asset) {
            // Vérifier si cet équipement est dans une réservation active/approuvée
            $activeReservation = Reservation::whereHas('assets', fn($q) => $q->where('assets.id', $asset->id))
                ->whereIn('status', ['approved', 'active'])
                ->first();

            if (!$activeReservation) {
                // Pas de réservation active → devrait être disponible
                $toRelease[] = [
                    'asset' => $asset,
                    'reason' => 'Aucune réservation active/approuvée',
                ];
                continue;
            }

            // Vérifier si la réservation est "active" (tous les équipements scannés)
            if ($activeReservation->status === 'active') {
                // Vérifier si cet équipement a été scanné (checkout_at non null)
                $pivot = $activeReservation->assets()->where('assets.id', $asset->id)->first();

                if ($pivot && !$pivot->pivot->checkout_at) {
                    // Réservation active mais équipement pas scanné → devrait être disponible
                    $toRelease[] = [
                        'asset' => $asset,
                        'reason' => "Réservation #{$activeReservation->id} active mais équipement non scanné (probablement substitué)",
                        'reservation_id' => $activeReservation->id,
                    ];
                }
            }
        }

        if (empty($toRelease)) {
            $this->info('✅ Tous les équipements "reserved" sont correctement assignés.');
            return 0;
        }

        $this->warn("⚠️  " . count($toRelease) . " équipement(s) bloqué(s) détecté(s) :");
        $this->newLine();

        foreach ($toRelease as $item) {
            $asset = $item['asset'];
            $this->line("  • {$asset->name} ({$asset->asset_tag}) — {$item['reason']}");
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('ℹ️  Mode test activé : aucune modification appliquée.');
            $this->info('🔧 Pour libérer ces équipements, exécutez : php artisan app:release-stuck-reserved-assets');
            return 0;
        }

        // Demander confirmation (sauf si --force)
        if (!$this->option('force')) {
            if (!$this->confirm('Voulez-vous libérer ces équipements (reserved → available) ?', true)) {
                $this->info('❌ Opération annulée.');
                return 1;
            }
        }

        $released = 0;
        foreach ($toRelease as $item) {
            $asset = $item['asset'];
            $asset->update(['status' => 'available']);

            // Logger le mouvement
            AssetMovement::create([
                'asset_id' => $asset->id,
                'reservation_id' => $item['reservation_id'] ?? null,
                'user_id' => null,
                'action' => 'status_change',
                'notes' => "Libération automatique (reserved → available) — {$item['reason']}",
            ]);

            $this->info("  ✅ {$asset->name} ({$asset->asset_tag}) libéré");
            $released++;
        }

        $this->newLine();
        $this->info("🎉 {$released} équipement(s) libéré(s) avec succès !");

        return 0;
    }
}
