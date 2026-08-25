<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetPlaceAssignment;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AssetPlaceController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // PAGE PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $places = Place::orderBy('is_storage', 'desc')->orderBy('name')->get(['id', 'name', 'color', 'description', 'is_storage']);

        $assignments = AssetPlaceAssignment::with(['asset' => fn($q) => $q->with(['category:id,name']), 'place', 'assignedBy:id,name'])
            ->whereNull('returned_at')
            ->orderBy('assigned_at')
            ->get()
            ->map(fn($a) => $this->formatAssignment($a));

        $history = AssetPlaceAssignment::with(['asset:id,name,asset_tag', 'place:id,name,color', 'assignedBy:id,name'])
            ->whereNotNull('returned_at')
            ->orderByDesc('returned_at')
            ->limit(50)
            ->get()
            ->map(fn($a) => $this->formatAssignment($a));

        return Inertia::render('Attribution/Locations', [
            'places'      => $places,
            'assignments' => $assignments,
            'history'     => $history,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AFFECTER UN ÉQUIPEMENT À UN LIEU
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_tag'         => 'required|string',
            'place_id'          => 'required|exists:places,id',
            'return_expected_at' => 'nullable|date|after:now',
            'notes'             => 'nullable|string|max:1000',
        ]);

        $asset = Asset::where('asset_tag', $data['asset_tag'])->first();
        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$data['asset_tag']}"], 404);
        }

        // Refuser les équipements retirés
        if ($asset->status === 'retired') {
            return response()->json(['error' => "Cet équipement est retiré de l'inventaire et ne peut pas être affecté."], 422);
        }

        // Vérifier qu'il n'a pas déjà une affectation active
        if ($asset->activePlaceAssignment()->exists()) {
            $existing = $asset->activePlaceAssignment()->with('place')->first();
            return response()->json([
                'error' => "Cet équipement est déjà affecté à « {$existing->place->name} ». Retournez-le d'abord.",
            ], 422);
        }

        // Vérifier qu'il n'est pas en cours de réservation active
        $activeReservation = $asset->reservations()
            ->where('reservations.status', 'active')
            ->wherePivotNull('returned_at')
            ->first();
        if ($activeReservation) {
            return response()->json(['error' => "Cet équipement est en cours d'utilisation (réservation #{$activeReservation->id})."], 422);
        }

        $place = Place::findOrFail($data['place_id']);

        // Refuser l'affectation vers un lieu de stockage via cette interface
        if ($place->is_storage) {
            return response()->json(['error' => 'Utilisez le retour standard pour remettre un équipement dans un lieu de stockage.'], 422);
        }

        $assignment = AssetPlaceAssignment::create([
            'asset_id'           => $asset->id,
            'place_id'           => $data['place_id'],
            'assigned_by'        => Auth::id(),
            'assigned_at'        => now(),
            'return_expected_at' => $data['return_expected_at'] ?? null,
            'notes'              => $data['notes'] ?? null,
        ]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => Auth::id(),
            'action'   => 'place_assigned',
            'notes'    => "Affecté à « {$place->name} »" . ($data['return_expected_at'] ? ' jusqu\'au ' . \Carbon\Carbon::parse($data['return_expected_at'])->format('d/m/Y H:i') : ' (permanent)'),
        ]);

        // Mise à jour automatique du statut : Disponible → Sorti
        if ($asset->status === 'available') {
            $asset->update(['status' => 'checked_out']);
        }

        $assignment->load(['asset.category', 'place', 'assignedBy']);

        return response()->json($this->formatAssignment($assignment));
    }

    // ─────────────────────────────────────────────────────────────────────
    // VALIDER UN ASSET TAG (pour l'autocomplete avant affectation)
    // ─────────────────────────────────────────────────────────────────────

    public function validateTag(Request $request)
    {
        $request->validate(['asset_tag' => 'required|string']);
        $asset = Asset::with(['activePlaceAssignment.place', 'category:id,name'])
            ->where('asset_tag', $request->asset_tag)
            ->first();

        if (!$asset) {
            return response()->json(['found' => false, 'error' => 'Équipement introuvable'], 404);
        }

        $activeAssignment = $asset->activePlaceAssignment;
        $activeReservation = $asset->reservations()
            ->where('reservations.status', 'active')
            ->wherePivotNull('returned_at')
            ->first();

        return response()->json([
            'found'               => true,
            'id'                  => $asset->id,
            'name'                => $asset->name,
            'asset_tag'           => $asset->asset_tag,
            'status'              => $asset->status,
            'category'            => $asset->category?->name,
            'active_assignment'   => $activeAssignment ? $this->formatAssignment($activeAssignment->load(['place'])) : null,
            'active_reservation'  => $activeReservation ? ['id' => $activeReservation->id, 'title' => $activeReservation->title] : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RETOURNER UN ÉQUIPEMENT (depuis la page lieux)
    // ─────────────────────────────────────────────────────────────────────

    public function returnAsset(Request $request, AssetPlaceAssignment $assignment)
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        if ($assignment->returned_at) {
            return response()->json(['error' => 'Cet équipement a déjà été retourné.'], 422);
        }

        $assignment->update([
            'returned_at' => now(),
            'notes'       => $request->notes
                ? ($assignment->notes ? $assignment->notes . "\n[Retour] " . $request->notes : '[Retour] ' . $request->notes)
                : $assignment->notes,
        ]);

        AssetMovement::create([
            'asset_id' => $assignment->asset_id,
            'user_id'  => Auth::id(),
            'action'   => 'place_returned',
            'notes'    => "Retour depuis « {$assignment->place->name} »",
        ]);

        // Mise à jour automatique du statut : Sorti → Disponible
        $assignment->asset->refresh();
        if ($assignment->asset->status === 'checked_out') {
            $assignment->asset->update(['status' => 'available']);
        }

        return response()->json(['ok' => true, 'assignment' => $this->formatAssignment($assignment->fresh(['asset', 'place', 'assignedBy']))]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AFFECTATION EN LOT (plusieurs assets scannés d'un coup)
    // ─────────────────────────────────────────────────────────────────────

    public function bulkStore(Request $request)
    {
        $data = $request->validate([
            'asset_tags'          => 'required|array|min:1',
            'asset_tags.*'        => 'required|string',
            'place_id'            => 'required|exists:places,id',
            'return_expected_at'  => 'nullable|date|after:now',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $place = Place::findOrFail($data['place_id']);

        if ($place->is_storage) {
            return response()->json(['error' => 'Utilisez le retour standard pour remettre un équipement dans un lieu de stockage.'], 422);
        }

        $created = [];
        $skipped = [];

        foreach ($data['asset_tags'] as $tag) {
            $asset = Asset::where('asset_tag', $tag)->first();
            if (!$asset) {
                $skipped[] = ['tag' => $tag, 'reason' => 'Équipement introuvable'];
                continue;
            }

            if ($asset->status === 'retired') {
                $skipped[] = ['tag' => $tag, 'name' => $asset->name, 'reason' => 'Équipement retiré de l\'inventaire'];
                continue;
            }

            if ($asset->activePlaceAssignment()->exists()) {
                $existing = $asset->activePlaceAssignment()->with('place')->first();
                $skipped[] = ['tag' => $tag, 'name' => $asset->name, 'reason' => "Déjà affecté à « {$existing->place->name} »"];
                continue;
            }

            $activeReservation = $asset->reservations()
                ->where('reservations.status', 'active')
                ->wherePivotNull('returned_at')
                ->first();
            if ($activeReservation) {
                $skipped[] = ['tag' => $tag, 'name' => $asset->name, 'reason' => "En cours de réservation #{$activeReservation->id}"];
                continue;
            }

            $assignment = AssetPlaceAssignment::create([
                'asset_id'           => $asset->id,
                'place_id'           => $data['place_id'],
                'assigned_by'        => Auth::id(),
                'assigned_at'        => now(),
                'return_expected_at' => $data['return_expected_at'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id'  => Auth::id(),
                'action'   => 'place_assigned',
                'notes'    => "Affecté à « {$place->name} »" . ($data['return_expected_at'] ? ' jusqu\'au ' . \Carbon\Carbon::parse($data['return_expected_at'])->format('d/m/Y H:i') : ' (permanent)'),
            ]);

            // Mise à jour automatique du statut : Disponible → Sorti
            if ($asset->status === 'available') {
                $asset->update(['status' => 'checked_out']);
            }

            $assignment->load(['asset.category', 'place', 'assignedBy']);
            $created[] = $this->formatAssignment($assignment);
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RETOUR PAR SCAN (depuis la page lieux)
    // ─────────────────────────────────────────────────────────────────────

    public function scanReturn(Request $request)
    {
        $request->validate([
            'asset_tag' => 'required|string',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();
        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }

        $assignment = AssetPlaceAssignment::where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            return response()->json(['error' => "Cet équipement n'est pas affecté à un lieu."], 422);
        }

        $assignment->update(['returned_at' => now()]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => Auth::id(),
            'action'   => 'place_returned',
            'notes'    => "Retour (scan) depuis « {$assignment->place->name} »",
        ]);

        // Mise à jour automatique du statut : Sorti → Disponible
        if ($asset->status === 'checked_out') {
            $asset->update(['status' => 'available']);
        }

        return response()->json([
            'ok'         => true,
            'asset_name' => $asset->name,
            'place_name' => $assignment->place->name,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────────────────

    private function formatAssignment(AssetPlaceAssignment $a): array
    {
        return [
            'id'                 => $a->id,
            'assigned_at'        => $a->assigned_at?->format('d/m/Y H:i'),
            'return_expected_at' => $a->return_expected_at?->format('Y-m-d\TH:i'),
            'return_expected_at_label' => $a->return_expected_at?->format('d/m/Y H:i'),
            'returned_at'        => $a->returned_at?->format('d/m/Y H:i'),
            'is_permanent'       => $a->return_expected_at === null,
            'is_overdue'         => $a->isOverdue(),
            'notes'              => $a->notes,
            'asset'              => $a->asset ? [
                'id'        => $a->asset->id,
                'name'      => $a->asset->name,
                'asset_tag' => $a->asset->asset_tag,
                'status'    => $a->asset->status,
                'category'  => $a->asset->category?->name,
            ] : null,
            'place'              => $a->place ? [
                'id'    => $a->place->id,
                'name'  => $a->place->name,
                'color' => $a->place->color,
            ] : null,
            'assigned_by'        => $a->assignedBy ? ['id' => $a->assignedBy->id, 'name' => $a->assignedBy->name] : null,
        ];
    }
}
