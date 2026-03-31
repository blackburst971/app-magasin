<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\EquipmentModel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    /**
     * Page de sélection des modèles à inventorier.
     */
    public function index()
    {
        $models = EquipmentModel::withCount('assets')
            ->orderBy('name')
            ->get(['id', 'name', 'image'])
            ->map(fn($m) => [
                'id'          => $m->id,
                'name'        => $m->name,
                'image_url'   => $m->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($m->image) : null,
                'assets_count'=> $m->assets_count,
            ]);

        return Inertia::render('Inventory/Index', [
            'models' => $models,
        ]);
    }

    /**
     * Page de session d'inventaire pour les modèles sélectionnés.
     */
    public function session(Request $request)
    {
        $request->validate([
            'models' => 'required|array|min:1',
            'models.*' => 'integer|exists:equipment_models,id',
        ]);

        $modelIds = $request->models;

        $assets = Asset::whereIn('equipment_model_id', $modelIds)
            ->with('equipmentModel')
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'name'       => $a->name,
                'asset_tag'  => $a->asset_tag,
                'serial'     => $a->serial,
                'status'     => $a->status,
                'location'   => $a->location,
                'model_name' => $a->equipmentModel?->name,
                'image'      => $a->image
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($a->image)
                    : ($a->equipmentModel?->image
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($a->equipmentModel->image)
                        : null),
            ]);

        $models = EquipmentModel::whereIn('id', $modelIds)->get(['id', 'name']);

        return Inertia::render('Inventory/Session', [
            'assets'     => $assets,
            'modelNames' => $models->pluck('name')->toArray(),
        ]);
    }
}
