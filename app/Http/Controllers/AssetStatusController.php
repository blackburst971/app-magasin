<?php

namespace App\Http\Controllers;

use App\Models\AssetStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetStatusController extends Controller
{
    public function index()
    {
        $statuses = AssetStatus::orderBy('sort_order')->orderBy('label')->get();

        return Inertia::render('Admin/AssetStatuses', [
            'statuses'     => $statuses,
            'colorPalette' => AssetStatus::colorPalette(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key'                => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:asset_statuses,key'],
            'label'              => ['required', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:500'],
            'color'              => ['required', 'string', 'in:' . implode(',', array_keys(AssetStatus::colorPalette()))],
            'blocks_reservation' => ['boolean'],
            'is_active'          => ['boolean'],
            'user_visible'       => ['boolean'],
            'hide_from_users'    => ['boolean'],
            'sort_order'         => ['integer', 'min:0'],
        ], [
            'key.regex'  => 'La clé ne doit contenir que des lettres minuscules, chiffres et underscores.',
            'key.unique' => 'Cette clé est déjà utilisée par un autre statut.',
            'color.in'   => 'Couleur invalide.',
        ]);

        $data['is_system'] = false;
        AssetStatus::create($data);
        AssetStatus::clearCache();

        return back()->with('success', 'Statut créé.');
    }

    public function update(Request $request, AssetStatus $assetStatus)
    {
        $keyRule = $assetStatus->is_system
            ? ['required', 'string', 'max:50']   // système: key non modifiable via validation (on l'ignore ci-dessous)
            : ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:asset_statuses,key,' . $assetStatus->id];

        $data = $request->validate([
            'key'                => $keyRule,
            'label'              => ['required', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:500'],
            'color'              => ['required', 'string', 'in:' . implode(',', array_keys(AssetStatus::colorPalette()))],
            'blocks_reservation' => ['boolean'],
            'is_active'          => ['boolean'],
            'user_visible'       => ['boolean'],
            'hide_from_users'    => ['boolean'],
            'sort_order'         => ['integer', 'min:0'],
        ]);

        // Protéger la clé des statuts système
        if ($assetStatus->is_system) {
            unset($data['key']);
        }

        $assetStatus->update($data);
        AssetStatus::clearCache();

        return back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(AssetStatus $assetStatus)
    {
        if ($assetStatus->is_system) {
            return back()->withErrors(['general' => 'Impossible de supprimer un statut système.']);
        }

        // Remettre les assets avec ce statut à "available"
        \App\Models\Asset::where('status', $assetStatus->key)->update(['status' => 'available']);

        $assetStatus->delete();
        AssetStatus::clearCache();

        return back()->with('success', 'Statut supprimé. Les équipements concernés ont été remis à "Disponible".');
    }

    /**
     * Réordonner les statuts via drag & drop.
     */
    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array']);
        foreach ($request->order as $index => $id) {
            AssetStatus::where('id', $id)->update(['sort_order' => $index]);
        }
        AssetStatus::clearCache();
        return response()->json(['ok' => true]);
    }
}
