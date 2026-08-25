<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetComponent;
use Illuminate\Http\Request;

class AssetComponentController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // CRÉER UN COMPOSANT (propagation automatique aux équipements du même modèle)
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'quantity'    => 'nullable|integer|min:1|max:999',
        ]);

        $baseName = trim($data['name']);
        $desc     = $data['description'] ?? null;
        $qty      = $data['quantity']    ?? 1;
        $modelId  = $asset->equipment_model_id;

        // ── Propagation aux équipements du même modèle ──────────────────
        $propagatedCount = 0;

        if ($modelId) {
            // Récupérer tous les équipements du même modèle, triés par asset_tag
            $siblings = Asset::where('equipment_model_id', $modelId)
                ->orderBy('asset_tag')
                ->get(['id', 'name', 'asset_tag']);

            $component = null;

            foreach ($siblings as $sibling) {
                $suffix     = $this->extractNumberSuffix($sibling->name);
                $compName   = $baseName . ($suffix !== '' ? " #{$suffix}" : '');
                $maxOrder   = $sibling->components()->max('sort_order') ?? -1;

                $created = $sibling->components()->create([
                    'name'        => $compName,
                    'description' => $desc,
                    'quantity'    => $qty,
                    'sort_order'  => $maxOrder + 1,
                ]);

                if ($sibling->id === $asset->id) {
                    // Conserver la référence directe du composant créé pour cet asset
                    $component = $created;
                } else {
                    $propagatedCount++;
                }
            }
        } else {
            // Pas de modèle → création simple sans numérotation
            $maxOrder  = $asset->components()->max('sort_order') ?? -1;
            $component = $asset->components()->create([
                'name'        => $baseName,
                'description' => $desc,
                'quantity'    => $qty,
                'sort_order'  => $maxOrder + 1,
            ]);
        }

        return response()->json([
            'id'             => $component->id,
            'name'           => $component->name,
            'description'    => $component->description,
            'quantity'       => $component->quantity,
            'sort_order'     => $component->sort_order,
            'condition'      => $component->condition,
            'propagated_to'  => $propagatedCount,
            'base_name'      => $baseName,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // MODIFIER UN COMPOSANT (uniquement sur cet équipement)
    // ─────────────────────────────────────────────────────────────────────

    public function update(Request $request, Asset $asset, AssetComponent $component)
    {
        abort_if($component->asset_id !== $asset->id, 404);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'quantity'    => 'nullable|integer|min:1|max:999',
        ]);

        $component->update($data);

        return response()->json($component);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SUPPRIMER UN COMPOSANT
    // Si propagate=true et que l'asset a un modèle, supprime le composant
    // homonyme (même nom de base) sur tous les équipements du modèle.
    // ─────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, Asset $asset, AssetComponent $component)
    {
        abort_if($component->asset_id !== $asset->id, 404);

        $propagate = $request->boolean('propagate', false);
        $deletedCount = 0;

        if ($propagate && $asset->equipment_model_id) {
            // Extraire le nom de base (sans le suffixe " #N")
            $baseName = $this->stripNumberSuffix($component->name);

            // Supprimer les composants portant le même nom de base sur tous les assets du modèle
            $siblingIds = Asset::where('equipment_model_id', $asset->equipment_model_id)
                ->pluck('id');

            $deletedCount = AssetComponent::whereIn('asset_id', $siblingIds)
                ->get()
                ->filter(fn($c) => $this->stripNumberSuffix($c->name) === $baseName)
                ->each(fn($c) => $c->delete())
                ->count() - 1; // -1 pour ne pas compter l'asset courant
        } else {
            $component->delete();
        }

        return response()->json(['ok' => true, 'deleted_siblings' => max(0, $deletedCount)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // METTRE À JOUR LA CONDITION D'UN COMPOSANT
    // ─────────────────────────────────────────────────────────────────────

    public function updateCondition(Request $request, Asset $asset, AssetComponent $component)
    {
        abort_if($component->asset_id !== $asset->id, 404);

        $request->validate([
            'condition' => 'nullable|string|in:good,damaged,missing,defective',
        ]);

        $component->update(['condition' => $request->condition ?: null]);

        return response()->json(['ok' => true, 'condition' => $component->condition]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RÉORDONNER LES COMPOSANTS
    // ─────────────────────────────────────────────────────────────────────

    public function reorder(Request $request, Asset $asset)
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:asset_components,id',
        ]);

        foreach ($request->ids as $order => $id) {
            $asset->components()->where('id', $id)->update(['sort_order' => $order]);
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Extrait le numéro en fin de nom d'asset.
     * "Canon EOS R5 #3"  → "3"
     * "Canon EOS R5"     → ""
     */
    private function extractNumberSuffix(string $assetName): string
    {
        if (preg_match('/#(\d+)\s*$/', trim($assetName), $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Supprime le suffixe " #N" en fin de nom de composant.
     * "Câble alimentation #3" → "Câble alimentation"
     * "Câble alimentation"    → "Câble alimentation"
     */
    private function stripNumberSuffix(string $name): string
    {
        return rtrim(preg_replace('/ #\d+\s*$/', '', trim($name)));
    }
}
