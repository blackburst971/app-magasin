<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\RecyclingItem;
use Illuminate\Console\Command;

class UpdateRecyclingAssetsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-recycling-assets-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour le statut des équipements présents dans les fiches de déchèterie en "retiré"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Recherche des équipements liés aux fiches de déchèterie...');

        // Récupérer tous les recycling items qui ont un asset_id (non archivés)
        $items = RecyclingItem::whereNotNull('asset_id')
            ->whereNull('archived_at')
            ->with('asset')
            ->get();

        if ($items->isEmpty()) {
            $this->warn('Aucun équipement trouvé dans les fiches de déchèterie.');
            return 0;
        }

        $this->info("📦 {$items->count()} fiche(s) avec équipement(s) lié(s) trouvée(s).");

        $updated = 0;
        $alreadyRetired = 0;
        $notFound = 0;

        foreach ($items as $item) {
            $asset = $item->asset;

            if (!$asset) {
                $this->warn("⚠️  Asset ID {$item->asset_id} introuvable (fiche #{$item->id})");
                $notFound++;
                continue;
            }

            if ($asset->status === 'retired') {
                $this->line("✓ {$asset->name} ({$asset->asset_tag}) — déjà en statut 'retiré'");
                $alreadyRetired++;
                continue;
            }

            $oldStatus = $asset->status;
            $asset->update(['status' => 'retired']);
            $this->info("✅ {$asset->name} ({$asset->asset_tag}) — statut changé de '{$oldStatus}' → 'retired'");
            $updated++;
        }

        $this->newLine();
        $this->info("📊 Résumé :");
        $this->line("   • Mis à jour : {$updated}");
        $this->line("   • Déjà retirés : {$alreadyRetired}");
        if ($notFound > 0) {
            $this->line("   • Introuvables : {$notFound}");
        }

        return 0;
    }
}
