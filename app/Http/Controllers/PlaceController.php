<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\AssetPlaceAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlaceController extends Controller
{
    public function index()
    {
        $places = Place::withCount(['activeAssignments'])
            ->orderBy('is_storage', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'                       => $p->id,
                'name'                     => $p->name,
                'description'              => $p->description,
                'color'                    => $p->color,
                'is_storage'               => $p->is_storage,
                'active_assignments_count' => $p->active_assignments_count,
                'assets_count'             => \App\Models\Asset::where('place_id', $p->id)->count(),
            ]);

        return \Inertia\Inertia::render('Places/Index', [
            'places' => $places,
        ]);
    }

    public function show(Place $place)
    {
        $activeAssignments = AssetPlaceAssignment::with([
                'asset.category:id,name',
                'asset.equipmentModel:id,name',
                'assignedBy:id,name',
            ])
            ->where('place_id', $place->id)
            ->whereNull('returned_at')
            ->orderByDesc('assigned_at')
            ->get()
            ->map(fn($a) => [
                'id'                       => $a->id,
                'assigned_at'              => $a->assigned_at?->format('d/m/Y H:i'),
                'return_expected_at_label' => $a->return_expected_at?->format('d/m/Y H:i'),
                'is_permanent'             => $a->return_expected_at === null,
                'is_overdue'               => $a->isOverdue(),
                'notes'                    => $a->notes,
                'asset' => $a->asset ? [
                    'id'        => $a->asset->id,
                    'name'      => $a->asset->name,
                    'asset_tag' => $a->asset->asset_tag,
                    'status'    => $a->asset->status,
                    'category'  => $a->asset->category?->name,
                    'model'     => $a->asset->equipmentModel?->name,
                    'image'     => $a->asset->image
                        ? (\Illuminate\Support\Str::startsWith($a->asset->image, 'http')
                            ? $a->asset->image
                            : \Illuminate\Support\Facades\Storage::url($a->asset->image))
                        : null,
                ] : null,
                'assigned_by' => $a->assignedBy ? ['id' => $a->assignedBy->id, 'name' => $a->assignedBy->name] : null,
            ]);

        $history = AssetPlaceAssignment::with([
                'asset:id,name,asset_tag',
                'assignedBy:id,name',
            ])
            ->where('place_id', $place->id)
            ->whereNotNull('returned_at')
            ->orderByDesc('returned_at')
            ->limit(50)
            ->get()
            ->map(fn($a) => [
                'id'                       => $a->id,
                'assigned_at'              => $a->assigned_at?->format('d/m/Y H:i'),
                'returned_at'              => $a->returned_at?->format('d/m/Y H:i'),
                'return_expected_at_label' => $a->return_expected_at?->format('d/m/Y H:i'),
                'is_permanent'             => $a->return_expected_at === null,
                'asset' => $a->asset ? [
                    'id'        => $a->asset->id,
                    'name'      => $a->asset->name,
                    'asset_tag' => $a->asset->asset_tag,
                ] : null,
                'assigned_by' => $a->assignedBy ? ['name' => $a->assignedBy->name] : null,
            ]);

        // Toutes les autres places (pour navigation rapide)
        $allPlaces = Place::orderBy('is_storage', 'desc')->orderBy('name')
            ->withCount('activeAssignments')
            ->get(['id', 'name', 'color', 'is_storage']);

        return Inertia::render('Places/Show', [
            'place'             => array_merge($place->only('id', 'name', 'description', 'color', 'is_storage'), [
                'active_count' => $activeAssignments->count(),
            ]),
            'activeAssignments' => $activeAssignments,
            'history'           => $history,
            'allPlaces'         => $allPlaces,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'color'       => 'required|string|max:7',
            'is_storage'  => 'boolean',
        ]);

        $place = Place::create($data);

        return response()->json($place);
    }

    public function update(Request $request, Place $place)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'color'       => 'required|string|max:7',
            'is_storage'  => 'boolean',
        ]);

        $oldName = $place->name;
        $place->update($data);

        // Synchroniser le champ location (texte) sur tous les assets liés à ce lieu
        if ($data['name'] !== $oldName) {
            \App\Models\Asset::where('place_id', $place->id)
                ->update(['location' => $data['name']]);
        }

        return response()->json($place);
    }

    public function destroy(Place $place)
    {
        if ($place->activeAssignments()->exists()) {
            return response()->json(['error' => 'Ce lieu a des affectations actives. Retournez d\'abord les équipements.'], 422);
        }

        $place->delete();

        return response()->json(['ok' => true]);
    }
}
