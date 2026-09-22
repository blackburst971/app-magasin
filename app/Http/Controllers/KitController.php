<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use App\Models\KitCategory;
use App\Models\KitMissingItem;
use App\Models\Asset;
use App\Models\Reservation;
use App\Models\User;
use App\Mail\ReservationAwaitingSupervisor;
use App\Mail\ReservationPending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KitController extends Controller
{
    private function getImageUrl(?string $image): ?string
    {
        if (!$image) return null;
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return Storage::url($image);
    }

    /** Liste des kits — page admin */
    public function index()
    {
        $kits = Kit::with([
                'assets.category',
                'category',
                'pendingMissingItems.asset',
                'pendingMissingItems.responsibleUser',
                'reservations' => function($query) {
                    // Charger les réservations actives et approuvées pour calculer le statut
                    $query->whereIn('reservations.status', ['active', 'approved'])
                          ->orderByDesc('reservations.created_at');
                }
            ])
            ->withCount('assets')
            ->orderBy('name')
            ->get()
            ->map(fn($k) => [
                'id'             => $k->id,
                'name'           => $k->name,
                'description'    => $k->description,
                'notes'          => $k->notes,
                'condition_note' => $k->condition_note,
                'image'          => $this->getImageUrl($k->image),
                'status'         => $k->current_status, // Statut dynamique
                'status_label'   => $k->status_label,   // Label dynamique
                'status_color'   => $k->status_color,   // Couleur dynamique
                'is_reservable'  => $k->is_reservable,
                'assets_count'   => $k->assets_count,
                'assets'         => $k->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'category'  => $a->category?->name,
                    'image'     => $this->getImageUrl($a->image),
                ]),
                'category'     => $k->category ? ['id' => $k->category->id, 'name' => $k->category->name, 'color' => $k->category->color] : null,
                'in_preparation' => $k->assets->isNotEmpty() && $k->assets->every(fn($a) => $a->status === 'en_preparation'),
                'partially_in_preparation' => $k->assets->contains(fn($a) => $a->status === 'en_preparation'),
                'pending_missing_count' => $k->pendingMissingItems->count(),
                'pending_missing_items' => $k->pendingMissingItems->map(fn($m) => [
                    'id'               => $m->id,
                    'asset'            => ['id' => $m->asset->id, 'name' => $m->asset->name, 'asset_tag' => $m->asset->asset_tag],
                    'responsible_user' => $m->responsibleUser ? ['id' => $m->responsibleUser->id, 'name' => $m->responsibleUser->name] : null,
                    'notes'            => $m->notes,
                    'created_at'       => $m->created_at->format('d/m/Y H:i'),
                ]),
            ]);

        $availableAssets = Asset::with('category')
            ->whereDoesntHave('kits')
            ->whereNotIn('status', ['retired'])
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'category_id', 'image', 'is_reservable', 'status'])
            ->map(fn($a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'asset_tag'     => $a->asset_tag,
                'category'      => $a->category?->name,
                'image'         => $this->getImageUrl($a->image),
                'is_reservable' => (bool) $a->is_reservable,
                'status'        => $a->status,
            ]);

        $categories = KitCategory::orderBy('position')->orderBy('name')->get(['id', 'name', 'color', 'description', 'position']);

        return Inertia::render('Kits/Index', [
            'kits'            => $kits,
            'kitCategories'   => $categories,
            'availableAssets' => auth()->user()->hasRole('admin') ? $availableAssets : [],
        ]);
    }

    /** Créer un kit */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'notes'           => 'nullable|string|max:1000',
            'kit_category_id' => 'nullable|exists:kit_categories,id',
            'asset_ids'       => 'required|array|min:1',
            'asset_ids.*'     => 'exists:assets,id',
            'image'           => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('kits', 'public');
        }

        $kit = Kit::create([
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'image'           => $imagePath,
            'status'          => 'available',
            'kit_category_id' => $data['kit_category_id'] ?? null,
        ]);

        $kit->assets()->sync($data['asset_ids']);

        // Les équipements ajoutés à un kit ne sont plus réservables individuellement et passent au statut "in_kit"
        Asset::whereIn('id', $data['asset_ids'])->update([
            'is_reservable' => false,
            'status'        => 'in_kit',
        ]);

        return back()->with('success', "Kit « {$kit->name} » créé.");
    }

    /** Mettre à jour un kit */
    public function update(Request $request, Kit $kit)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string|max:1000',
            'notes'           => 'nullable|string|max:1000',
            'status'          => 'required|in:available,partial,unavailable',
            'is_reservable'   => 'boolean',
            'kit_category_id' => 'nullable|exists:kit_categories,id',
            'asset_ids'       => 'nullable|array',
            'asset_ids.*'     => 'exists:assets,id',
            'image'           => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
            'remove_image'    => 'nullable|boolean',
        ]);

        $imagePath = $kit->image;

        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image
            if ($imagePath) Storage::disk('public')->delete($imagePath);
            $imagePath = $request->file('image')->store('kits', 'public');
        } elseif (!empty($data['remove_image'])) {
            if ($imagePath) Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        $kit->update([
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'notes'           => $data['notes'] ?? null,
            'image'           => $imagePath,
            'status'          => $data['status'],
            'is_reservable'   => $data['is_reservable'] ?? $kit->is_reservable,
            'kit_category_id' => $data['kit_category_id'] ?? null,
        ]);

        if (isset($data['asset_ids'])) {
            $oldAssetIds = $kit->assets->pluck('id')->toArray();
            $newAssetIds = $data['asset_ids'];

            $removedIds = array_diff($oldAssetIds, $newAssetIds);
            $addedIds   = array_diff($newAssetIds, $oldAssetIds);

            $kit->assets()->sync($newAssetIds);

            // Restaurer is_reservable et status pour les équipements retirés du kit (si pas dans un autre kit)
            foreach ($removedIds as $id) {
                $asset = Asset::find($id);
                if ($asset && !$asset->kits()->exists()) {
                    $asset->update([
                        'is_reservable' => true,
                        'status'        => 'available',
                    ]);
                }
            }

            // Masquer de la réservation les équipements ajoutés au kit et mettre à jour leur statut
            if ($addedIds) {
                Asset::whereIn('id', $addedIds)->update([
                    'is_reservable' => false,
                    'status'        => 'in_kit',
                ]);
            }
        }

        return back()->with('success', 'Kit mis à jour.');
    }

    /** Supprimer un kit */
    public function destroy(Kit $kit)
    {
        $assetIds = $kit->assets->pluck('id')->toArray();
        $kit->assets()->detach();
        $kit->delete();

        // Restaurer is_reservable et status pour les équipements qui ne sont plus dans aucun kit
        foreach ($assetIds as $id) {
            $asset = Asset::find($id);
            if ($asset && !$asset->kits()->exists()) {
                $asset->update([
                    'is_reservable' => true,
                    'status'        => 'available',
                ]);
            }
        }

        return back()->with('success', 'Kit supprimé.');
    }

    /** Mettre à jour la note d'état du kit (visible par les utilisateurs) */
    public function updateConditionNote(Request $request, Kit $kit)
    {
        $request->validate(['condition_note' => 'nullable|string|max:2000']);
        $kit->update(['condition_note' => $request->condition_note ?: null]);
        return response()->json(['ok' => true]);
    }

    /**
     * Rechercher un équipement par code-barres pour l'ajouter à un kit.
     * Admin only.
     */
    public function assetLookup(Request $request)
    {
        $request->validate(['asset_tag' => 'required|string']);

        $asset = Asset::with('category')->where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json(['found' => false, 'error' => 'Équipement introuvable.'], 404);
        }

        if ($asset->status === 'retired') {
            return response()->json(['found' => false, 'error' => "Équipement retiré de l'inventaire."], 422);
        }

        $inKit = $asset->kits()->first();

        return response()->json([
            'found'         => true,
            'id'            => $asset->id,
            'name'          => $asset->name,
            'asset_tag'     => $asset->asset_tag,
            'category'      => $asset->category?->name,
            'image'         => $this->getImageUrl($asset->image),
            'is_reservable' => (bool) $asset->is_reservable,
            'status'        => $asset->status,
            'in_kit'        => $inKit ? ['id' => $inKit->id, 'name' => $inKit->name] : null,
        ]);
    }

    /**
     * Réserver un kit depuis la page équipement (utilisateur).
     * Crée une réservation avec tous les assets du kit.
     */
    public function reserve(Request $request, Kit $kit)
    {
        if (!$kit->is_reservable || $kit->status === 'unavailable') {
            return back()->withErrors(['kit' => 'Ce kit n\'est pas disponible à la réservation.']);
        }

        $data = $request->validate([
            'start_date' => 'required|date|after_or_equal:now',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();

        // Vérifier disponibilité des assets du kit
        $start = \Carbon\Carbon::parse($data['start_date']);
        $end   = \Carbon\Carbon::parse($data['end_date']);

        foreach ($kit->assets as $asset) {
            if (!$asset->isAvailableForPeriod($start, $end)) {
                return back()->withErrors(['kit' => "L'équipement « {$asset->name} » du kit n'est pas disponible sur cette période."]);
            }
        }

        // Vérifier si l'utilisateur doit passer par un superviseur
        $bypassSupervisor = $user->hasPermissionTo('bypass_supervisor_approval');
        $supervisor       = null;
        if (!$bypassSupervisor) {
            $supervisor = $user->getSupervisor();
        }
        $initialStatus = $supervisor ? 'awaiting_supervisor' : 'pending';

        $reservation = Reservation::create([
            'user_id'    => $user->id,
            'kit_id'     => $kit->id,
            'title'      => "Kit : {$kit->name}",
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'notes'      => $data['notes'] ?? null,
            'status'     => $initialStatus,
        ]);

        $reservation->assets()->attach($kit->assets->pluck('id'));

        // Notifications selon le flux
        if ($supervisor) {
            // Notifier le superviseur
            $supervisor->notify(new \App\Notifications\ReservationAwaitingSupervisorNotification($reservation));
            try {
                Mail::to($supervisor->email)->send(new ReservationAwaitingSupervisor($reservation, $supervisor));
            } catch (\Exception $e) {}

            return redirect()->route('reservations.show', $reservation->id)
                ->with('success', "Demande de kit « {$kit->name} » envoyée à votre responsable pour validation.");
        }

        // Pas de superviseur ou bypass → notifier les admins directement
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\ReservationPendingNotification($reservation));
            try {
                Mail::to($admin->email)->send(new ReservationPending($reservation, $admin));
            } catch (\Exception $e) {}
        }

        return redirect()->route('reservations.show', $reservation->id)
            ->with('success', "Réservation du kit « {$kit->name} » créée.");
    }

    /**
     * Signaler un élément manquant dans un kit lors d'un retour.
     * Appelé depuis la page Retours.
     */
    public function markMissing(Request $request)
    {
        $data = $request->validate([
            'kit_id'              => 'required|exists:kits,id',
            'asset_id'            => 'required|exists:assets,id',
            'reservation_id'      => 'nullable|exists:reservations,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'notes'               => 'nullable|string|max:500',
        ]);

        // Éviter les doublons sur la même réservation
        $existing = KitMissingItem::where('kit_id', $data['kit_id'])
            ->where('asset_id', $data['asset_id'])
            ->where('reservation_id', $data['reservation_id'])
            ->whereNull('regularized_at')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Cet équipement est déjà signalé manquant.'], 409);
        }

        $missing = KitMissingItem::create($data);

        // Mettre à jour le statut du kit : partial si pas toutes les pièces concernées, unavailable si admin décide
        $kit = Kit::find($data['kit_id']);
        if ($kit->status === 'available') {
            $kit->update(['status' => 'partial']);
        }

        // Marquer la réservation avec has_missing_items
        if ($data['reservation_id']) {
            Reservation::where('id', $data['reservation_id'])->update(['has_missing_items' => true]);
        }

        // Marquer l'asset pivot comme missing
        if ($data['reservation_id']) {
            $reservation = Reservation::find($data['reservation_id']);
            $reservation->assets()->updateExistingPivot($data['asset_id'], ['is_missing' => true]);
        }

        return response()->json([
            'success' => true,
            'missing_item' => [
                'id'      => $missing->id,
                'asset'   => Asset::find($data['asset_id'])?->only(['id', 'name', 'asset_tag']),
            ],
        ]);
    }

    /**
     * Retourne le statut actuel de plusieurs kits (pour rafraîchir le panier).
     * GET /api/kits/statuses?ids[]=1&ids[]=2
     */
    public function statuses(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        if (empty($ids)) return response()->json([]);

        $kits = Kit::with('assets')->whereIn('id', $ids)->get();

        $statusLabels = [
            'available'   => 'Disponible',
            'unavailable' => 'Indisponible',
            'partial'     => 'Partiel',
            'missing'     => 'Incomplet',
        ];

        return response()->json($kits->map(fn($kit) => [
            'id'           => $kit->id,
            'status'       => $kit->status,
            'status_label' => $statusLabels[$kit->status] ?? $kit->status,
        ])->values());
    }

    /**
     * Vérifie la disponibilité d'un ou plusieurs kits sur une période donnée.
     * GET /api/kits/availability?kit_ids[]=1&kit_ids[]=2&start=...&end=...
     */
    public function checkAvailability(Request $request)
    {
        $kitIds = array_filter(array_map('intval', (array) $request->input('kit_ids', [])));
        $start  = $request->input('start');
        $end    = $request->input('end');

        if (empty($kitIds) || !$start || !$end) {
            return response()->json([]);
        }

        $startDt = new \DateTime($start);
        $endDt   = new \DateTime($end);

        $kits = Kit::with('assets')->whereIn('id', $kitIds)->get();

        return response()->json($kits->map(function ($kit) use ($startDt, $endDt) {
            if (!$kit->is_reservable || $kit->status === 'unavailable') {
                return [
                    'id'     => $kit->id,
                    'status' => 'unavailable',
                    'reason' => 'Kit non disponible à la réservation.',
                ];
            }

            $worstStatus = 'available';
            $worstReason = null;

            foreach ($kit->assets as $asset) {
                $avail = $asset->getAvailabilityForPeriod($startDt, $endDt);
                if ($avail['status'] === 'unavailable') {
                    $worstStatus = 'unavailable';
                    $worstReason = $avail['reason'];
                    break; // Un seul asset indisponible suffit
                } elseif ($avail['status'] === 'warning' && $worstStatus === 'available') {
                    $worstStatus = 'warning';
                    $worstReason = $avail['reason'];
                }
            }

            return [
                'id'     => $kit->id,
                'status' => $worstStatus,
                'reason' => $worstReason,
            ];
        })->values());
    }

    // ─── Catégories de kits ───────────────────────────────────────────────────

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);
        $data['position'] = KitCategory::max('position') + 1;
        $category = KitCategory::create($data);
        return response()->json(['id' => $category->id, 'name' => $category->name, 'color' => $category->color, 'description' => $category->description, 'position' => $category->position]);
    }

    public function updateCategory(Request $request, KitCategory $kitCategory)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);
        $kitCategory->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroyCategory(KitCategory $kitCategory)
    {
        // Détacher les kits liés (nullable FK, mis à null automatiquement)
        $kitCategory->delete();
        return back()->with('success', "Catégorie « {$kitCategory->name} » supprimée.");
    }

    /**
     * Régulariser un élément manquant (admin).
     */
    public function regularize(Request $request, KitMissingItem $missingItem)
    {
        $data = $request->validate([
            'regularization_notes' => 'nullable|string|max:500',
            'kit_status'           => 'required|in:available,partial,unavailable',
        ]);

        $missingItem->update([
            'regularized_at'       => now(),
            'regularization_notes' => $data['regularization_notes'] ?? null,
        ]);

        // Mettre à jour le statut du kit
        $kit = $missingItem->kit;
        $kit->update(['status' => $data['kit_status']]);

        // Si plus d'éléments manquants non régularisés → retirer has_missing_items des réservations concernées
        if ($kit->pendingMissingItems()->count() === 0 && $data['kit_status'] !== 'unavailable') {
            $kit->update(['is_reservable' => true]);
        }

        return back()->with('success', 'Élément régularisé. Statut du kit mis à jour.');
    }
}
