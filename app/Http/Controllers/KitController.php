<?php

namespace App\Http\Controllers;

use App\Models\Kit;
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
        $kits = Kit::with(['assets.category', 'pendingMissingItems.asset', 'pendingMissingItems.responsibleUser'])
            ->withCount('assets')
            ->orderBy('name')
            ->get()
            ->map(fn($k) => [
                'id'           => $k->id,
                'name'         => $k->name,
                'description'  => $k->description,
                'notes'        => $k->notes,
                'image'        => $this->getImageUrl($k->image),
                'status'       => $k->status,
                'status_label' => $k->status_label,
                'status_color' => $k->status_color,
                'is_reservable'=> $k->is_reservable,
                'assets_count' => $k->assets_count,
                'assets'       => $k->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'category'  => $a->category?->name,
                    'image'     => $this->getImageUrl($a->image),
                ]),
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
            ->where('is_reservable', true)
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'category_id', 'image'])
            ->map(fn($a) => [
                'id'        => $a->id,
                'name'      => $a->name,
                'asset_tag' => $a->asset_tag,
                'category'  => $a->category?->name,
                'image'     => $this->getImageUrl($a->image),
            ]);

        return Inertia::render('Kits/Index', [
            'kits'            => $kits,
            'availableAssets' => auth()->user()->hasRole('admin') ? $availableAssets : [],
        ]);
    }

    /** Créer un kit */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'notes'       => 'nullable|string|max:1000',
            'asset_ids'   => 'required|array|min:1',
            'asset_ids.*' => 'exists:assets,id',
            'image'       => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('kits', 'public');
        }

        $kit = Kit::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'image'       => $imagePath,
            'status'      => 'available',
        ]);

        $kit->assets()->sync($data['asset_ids']);

        return back()->with('success', "Kit « {$kit->name} » créé.");
    }

    /** Mettre à jour un kit */
    public function update(Request $request, Kit $kit)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'notes'        => 'nullable|string|max:1000',
            'status'       => 'required|in:available,partial,unavailable',
            'is_reservable'=> 'boolean',
            'asset_ids'    => 'nullable|array',
            'asset_ids.*'  => 'exists:assets,id',
            'image'        => 'nullable|image|max:2048',
            'remove_image' => 'nullable|boolean',
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
            'name'         => $data['name'],
            'description'  => $data['description'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'image'        => $imagePath,
            'status'       => $data['status'],
            'is_reservable'=> $data['is_reservable'] ?? $kit->is_reservable,
        ]);

        if (isset($data['asset_ids'])) {
            $kit->assets()->sync($data['asset_ids']);
        }

        return back()->with('success', 'Kit mis à jour.');
    }

    /** Supprimer un kit */
    public function destroy(Kit $kit)
    {
        $kit->assets()->detach();
        $kit->delete();
        return back()->with('success', 'Kit supprimé.');
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
