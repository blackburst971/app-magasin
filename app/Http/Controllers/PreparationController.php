<?php

namespace App\Http\Controllers;

use App\Mail\CheckoutSummary;
use App\Mail\PreparationReadyMail;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Kit;
use App\Models\Preparation;
use App\Models\PreparationAsset;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PreparationController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // PAGE PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        // Préparations classiques (libres ou liées à une réservation avec ajout auto)
        $preparations = Preparation::with([
            'user:id,name,email',
            'preparedBy:id,name',
            'validatedBy:id,name',
            'reservation:id,title,status',
            'assets' => fn($q) => $q->with('category:id,name')->select('assets.id', 'assets.name', 'assets.asset_tag', 'assets.status', 'assets.image'),
        ])
            ->where('status', 'preparing')
            ->where('mode', 'free')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->formatPreparation($p));

        // Préparations issues d'une réservation (scan obligatoire)
        $reservationPreparations = Preparation::with([
            'user:id,name,email',
            'preparedBy:id,name',
            'reservation' => fn($q) => $q->with(['assets' => fn($q2) => $q2->with('category:id,name')->select('assets.id', 'assets.name', 'assets.asset_tag', 'assets.status', 'assets.image', 'assets.category_id')]),
            'preparationAssets',
        ])
            ->where('status', 'preparing')
            ->where('mode', 'reservation')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->formatReservationPreparation($p));

        $approvedReservations = Reservation::with(['user:id,name', 'assets:id,name,asset_tag'])
            ->whereIn('status', ['approved', 'pending'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'status'      => $r->status,
                'status_label'=> $r->status_label,
                'user'        => ['id' => $r->user->id, 'name' => $r->user->name],
                'start_date'  => $r->start_date?->format('Y-m-d\TH:i'),
                'end_date'    => $r->end_date?->format('Y-m-d\TH:i'),
                'asset_count' => $r->assets->count(),
            ]);

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Attribution/Preparation', [
            'preparations'            => $preparations,
            'reservationPreparations' => $reservationPreparations,
            'approvedReservations'    => $approvedReservations,
            'users'                   => $users,
            'defaultTab'              => request('tab', 'active'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRÉER UNE NOUVELLE PRÉPARATION
    // ─────────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'user_id'               => 'required|exists:users,id',
            'planned_start_at'      => 'required|date',
            'planned_end_at'        => 'required|date|after:planned_start_at',
            'auto_checkout_at'      => 'nullable|date',
            'notes'                 => 'nullable|string|max:2000',
            'reservation_id'        => 'nullable|exists:reservations,id',
            'send_preparation_mail' => 'boolean',
            'send_checkout_mail'    => 'boolean',
        ]);

        // Si liée à une réservation existante, vérifier que la réservation n'a pas déjà une préparation active
        if (!empty($data['reservation_id'])) {
            $existing = Preparation::where('reservation_id', $data['reservation_id'])
                ->where('status', 'preparing')
                ->first();
            if ($existing) {
                return response()->json([
                    'error' => 'Cette réservation a déjà une préparation en cours (préparation #' . $existing->id . ').',
                ], 422);
            }
        }

        $preparation = Preparation::create([
            'prepared_by'           => Auth::id(),
            'user_id'               => $data['user_id'],
            'title'                 => $data['title'],
            'planned_start_at'      => $data['planned_start_at'],
            'planned_end_at'        => $data['planned_end_at'],
            'auto_checkout_at'      => $data['auto_checkout_at'] ?? null,
            'notes'                 => $data['notes'] ?? null,
            'status'                => 'preparing',
            'reservation_id'        => $data['reservation_id'] ?? null,
            'mode'                  => !empty($data['reservation_id']) ? 'reservation' : 'free',
            'send_preparation_mail' => $data['send_preparation_mail'] ?? false,
            'send_checkout_mail'    => $data['send_checkout_mail'] ?? false,
        ]);

        // Si liée à une réservation, pré-scanner automatiquement les assets déjà dans la réservation
        if (!empty($data['reservation_id'])) {
            $reservation = Reservation::with('assets')->find($data['reservation_id']);
            foreach ($reservation->assets as $asset) {
                $previousStatus = $asset->status;
                PreparationAsset::create([
                    'preparation_id'  => $preparation->id,
                    'asset_id'        => $asset->id,
                    'previous_status' => $previousStatus,
                    'scanned_at'      => now(),
                ]);
                $asset->update(['status' => 'en_preparation']);
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'user_id'  => Auth::id(),
                    'action'   => 'status_change',
                    'notes'    => "Mis en préparation — Préparation #{$preparation->id}",
                ]);
            }
        }

        $preparation->load(['user', 'preparedBy', 'assets.category']);

        return response()->json($this->formatPreparation($preparation));
    }

    // ─────────────────────────────────────────────────────────────────────
    // VALIDER UN ASSET TAG (scan)
    // ─────────────────────────────────────────────────────────────────────

    public function validateTag(Request $request)
    {
        $request->validate([
            'asset_tag'      => 'required|string',
            'preparation_id' => 'required|exists:preparations,id',
        ]);

        $asset = Asset::with('category:id,name')
            ->where('asset_tag', $request->asset_tag)
            ->first();

        if (!$asset) {
            return response()->json(['found' => false, 'error' => 'Équipement introuvable'], 404);
        }

        $preparation = Preparation::findOrFail($request->preparation_id);

        // Déjà dans cette préparation ?
        $alreadyInThis = $preparation->assets()->where('assets.id', $asset->id)->exists();
        if ($alreadyInThis) {
            return response()->json(['found' => true, 'error' => 'Cet équipement est déjà dans cette préparation.', 'already_added' => true]);
        }

        // En cours dans une autre préparation ?
        $otherPrep = PreparationAsset::whereHas('preparation', fn($q) => $q->where('status', 'preparing'))
            ->where('asset_id', $asset->id)
            ->with('preparation')
            ->first();
        if ($otherPrep) {
            return response()->json([
                'found' => true,
                'error' => "Cet équipement est déjà en préparation (Préparation #{$otherPrep->preparation_id}).",
            ], 422);
        }

        // Vérifier disponibilité (hors statuts bloquants)
        if ($asset->status === 'retired' || $asset->status === 'maintenance') {
            return response()->json([
                'found' => true,
                'error' => "Cet équipement est indisponible (statut : {$asset->status}).",
            ], 422);
        }

        return response()->json([
            'found'    => true,
            'id'       => $asset->id,
            'name'     => $asset->name,
            'asset_tag' => $asset->asset_tag,
            'status'   => $asset->status,
            'category' => $asset->category?->name,
            'image'     => $this->imageUrl($asset->image),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJOUTER UN ÉQUIPEMENT À UNE PRÉPARATION
    // ─────────────────────────────────────────────────────────────────────

    public function addAsset(Request $request, Preparation $preparation)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation n\'est plus modifiable.'], 422);
        }

        $request->validate(['asset_tag' => 'required|string']);

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();
        if (!$asset) {
            return response()->json(['error' => 'Équipement introuvable.'], 404);
        }

        if ($preparation->assets()->where('assets.id', $asset->id)->exists()) {
            return response()->json(['error' => 'Cet équipement est déjà dans cette préparation.'], 422);
        }

        $otherPrep = PreparationAsset::whereHas('preparation', fn($q) => $q->where('status', 'preparing'))
            ->where('asset_id', $asset->id)
            ->first();
        if ($otherPrep) {
            return response()->json([
                'error' => "Cet équipement est déjà en préparation (Préparation #{$otherPrep->preparation_id}).",
            ], 422);
        }

        if ($asset->status === 'retired' || $asset->status === 'maintenance') {
            return response()->json(['error' => "Équipement indisponible (statut : {$asset->status})."], 422);
        }

        $previousStatus = $asset->status;

        PreparationAsset::create([
            'preparation_id'  => $preparation->id,
            'asset_id'        => $asset->id,
            'previous_status' => $previousStatus,
            'scanned_at'      => now(),
        ]);

        $asset->update(['status' => 'en_preparation']);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => Auth::id(),
            'action'   => 'status_change',
            'notes'    => "Mis en préparation — Préparation #{$preparation->id}",
        ]);

        $asset->load('category');

        return response()->json([
            'ok'    => true,
            'asset' => [
                'id'        => $asset->id,
                'name'      => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'status'    => $asset->status,
                'category'  => $asset->category?->name,
                'image'     => $this->imageUrl($asset->image),
                'previous_status' => $previousStatus,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECHERCHER DES KITS
    // ─────────────────────────────────────────────────────────────────────

    public function searchKits(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100']);
        $q = trim($request->input('q', ''));

        $kits = Kit::with(['assets' => fn($q) => $q->with('category:id,name')->select('assets.id', 'name', 'asset_tag', 'status', 'image', 'category_id')])
            ->where('is_reservable', true)
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn($kit) => [
                'id'     => $kit->id,
                'name'   => $kit->name,
                'status' => $kit->status,
                'assets' => $kit->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'status'    => $a->status,
                    'image'     => $this->imageUrl($a->image),
                    'category'  => $a->category?->name,
                ])->values()->all(),
            ]);

        return response()->json(['kits' => $kits]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AJOUTER UN KIT À UNE PRÉPARATION (tous ses équipements)
    // ─────────────────────────────────────────────────────────────────────

    public function addKit(Request $request, Preparation $preparation)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation n\'est plus modifiable.'], 422);
        }

        $request->validate(['kit_id' => 'required|exists:kits,id']);

        $kit = Kit::with(['assets.category'])->findOrFail($request->kit_id);

        $added   = [];
        $skipped = [];

        foreach ($kit->assets as $asset) {
            // Déjà dans cette préparation ?
            if ($preparation->assets()->where('assets.id', $asset->id)->exists()) {
                $skipped[] = ['asset_tag' => $asset->asset_tag, 'name' => $asset->name, 'reason' => 'Déjà dans cette préparation'];
                continue;
            }

            // En cours dans une autre préparation ?
            $otherPrep = PreparationAsset::whereHas('preparation', fn($q) => $q->where('status', 'preparing'))
                ->where('asset_id', $asset->id)
                ->first();
            if ($otherPrep) {
                $skipped[] = ['asset_tag' => $asset->asset_tag, 'name' => $asset->name, 'reason' => "En préparation #" . $otherPrep->preparation_id];
                continue;
            }

            if ($asset->status === 'retired' || $asset->status === 'maintenance') {
                $skipped[] = ['asset_tag' => $asset->asset_tag, 'name' => $asset->name, 'reason' => "Statut : {$asset->status}"];
                continue;
            }

            $previousStatus = $asset->status;

            PreparationAsset::create([
                'preparation_id'  => $preparation->id,
                'asset_id'        => $asset->id,
                'kit_id'          => $kit->id,
                'previous_status' => $previousStatus,
                'scanned_at'      => now(),
            ]);

            $asset->update(['status' => 'en_preparation']);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id'  => Auth::id(),
                'action'   => 'status_change',
                'notes'    => "Mis en préparation via kit « {$kit->name} » — Préparation #{$preparation->id}",
            ]);

            $added[] = [
                'id'              => $asset->id,
                'name'            => $asset->name,
                'asset_tag'       => $asset->asset_tag,
                'status'          => 'en_preparation',
                'category'        => $asset->category?->name,
                'image'     => $this->imageUrl($asset->image),
                'previous_status' => $previousStatus,
                'kit'             => ['id' => $kit->id, 'name' => $kit->name],
            ];
        }

        // Marquer le kit comme indisponible si au moins un équipement est ajouté
        if ($added) {
            $kit->update(['status' => 'unavailable']);
        }

        return response()->json([
            'ok'       => true,
            'added'    => $added,
            'skipped'  => $skipped,
            'kit_name' => $kit->name,
            'kit'      => ['id' => $kit->id, 'name' => $kit->name],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RETIRER UN ÉQUIPEMENT D'UNE PRÉPARATION
    // ─────────────────────────────────────────────────────────────────────

    public function removeAsset(Request $request, Preparation $preparation, Asset $asset)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation n\'est plus modifiable.'], 422);
        }

        $pa = PreparationAsset::where('preparation_id', $preparation->id)
            ->where('asset_id', $asset->id)
            ->firstOrFail();

        $kitId = $pa->kit_id;

        // Restaurer le statut précédent
        $asset->update(['status' => $pa->previous_status]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => Auth::id(),
            'action'   => 'status_change',
            'notes'    => "Retiré de la préparation #{$preparation->id} — statut restauré : {$pa->previous_status}",
        ]);

        $pa->delete();

        // Si l'asset appartenait à un kit, vérifier s'il reste des assets du kit en préparation
        if ($kitId) {
            $this->recalculateKitStatus($kitId);
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // VALIDER LA SORTIE (transformer en réservation active)
    // ─────────────────────────────────────────────────────────────────────

    public function validateCheckout(Request $request, Preparation $preparation)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation ne peut pas être validée dans son état actuel.'], 422);
        }

        if ($preparation->assets()->count() === 0) {
            return response()->json(['error' => 'Aucun équipement dans cette préparation.'], 422);
        }

        // Mode réservation : tous les équipements doivent avoir été scannés
        if ($preparation->mode === 'reservation' && $preparation->reservation_id) {
            $totalAssets   = Reservation::findOrFail($preparation->reservation_id)->assets()->count();
            $scannedAssets = $preparation->assets()->count();
            if ($scannedAssets < $totalAssets) {
                return response()->json([
                    'error' => "Tous les équipements de la réservation doivent être scannés avant de valider ({$scannedAssets}/{$totalAssets}).",
                ], 422);
            }
        }

        $preparation->load(['assets', 'user', 'preparationAssets']);
        $kitIds = $preparation->preparationAssets->pluck('kit_id')->filter()->unique()->toArray();

        if ($preparation->reservation_id) {
            // Cas 1 : réservation existante → activer
            $reservation = Reservation::findOrFail($preparation->reservation_id);

            $reservation->update([
                'status'      => 'active',
                'checkout_at' => now(),
            ]);

            foreach ($preparation->assets as $asset) {
                // Attacher à la réservation si pas déjà attaché
                if (!$reservation->assets()->where('assets.id', $asset->id)->exists()) {
                    $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
                } else {
                    $reservation->assets()->updateExistingPivot($asset->id, ['checkout_at' => now()]);
                }
                $asset->update([
                    'status'                 => 'checked_out',
                    'checked_out_to_user_id' => $preparation->user_id,
                    'expected_return_at'     => $preparation->planned_end_at,
                ]);
                AssetMovement::create([
                    'asset_id'       => $asset->id,
                    'reservation_id' => $reservation->id,
                    'user_id'        => Auth::id(),
                    'action'         => 'checkout',
                    'notes'          => "Sortie validée depuis préparation #{$preparation->id}",
                ]);
            }
        } else {
            // Cas 2 : préparation libre → créer une réservation active
            $reservation = Reservation::create([
                'user_id'     => $preparation->user_id,
                'title'       => $preparation->title,
                'start_date'  => $preparation->planned_start_at,
                'end_date'    => $preparation->planned_end_at,
                'status'      => 'active',
                'notes'       => $preparation->notes,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'checkout_at' => now(),
            ]);

            foreach ($preparation->assets as $asset) {
                $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
                $asset->update([
                    'status'                 => 'checked_out',
                    'checked_out_to_user_id' => $preparation->user_id,
                    'expected_return_at'     => $preparation->planned_end_at,
                ]);
                AssetMovement::create([
                    'asset_id'       => $asset->id,
                    'reservation_id' => $reservation->id,
                    'user_id'        => Auth::id(),
                    'action'         => 'checkout',
                    'notes'          => "Sortie validée depuis préparation #{$preparation->id}",
                ]);
            }

            // Mettre à jour la préparation avec la réservation créée
            $preparation->reservation_id = $reservation->id;
        }

        $preparation->update([
            'status'       => 'validated',
            'validated_at' => now(),
            'validated_by' => Auth::id(),
        ]);

        // Recalculer les kits — assets passent de en_preparation à checked_out
        foreach ($kitIds as $kitId) {
            $this->recalculateKitStatus($kitId);
        }

        // Envoyer le récapitulatif de sortie si demandé
        if ($preparation->send_checkout_mail) {
            try {
                $reservation->load(['user', 'assets.category']);
                Mail::to($preparation->user->email)->send(new CheckoutSummary($reservation));
            } catch (\Exception $e) {}
        }

        return response()->json([
            'ok'             => true,
            'reservation_id' => $reservation->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ANNULER UNE PRÉPARATION
    // ─────────────────────────────────────────────────────────────────────

    public function cancel(Request $request, Preparation $preparation)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation ne peut pas être annulée.'], 422);
        }

        $preparation->load(['assets', 'preparationAssets']);

        $kitIds = [];
        foreach ($preparation->preparationAssets as $pa) {
            $pa->asset->update(['status' => $pa->previous_status]);
            AssetMovement::create([
                'asset_id' => $pa->asset_id,
                'user_id'  => Auth::id(),
                'action'   => 'status_change',
                'notes'    => "Préparation #{$preparation->id} annulée — statut restauré : {$pa->previous_status}",
            ]);
            if ($pa->kit_id) $kitIds[] = $pa->kit_id;
        }

        $preparation->update(['status' => 'cancelled']);

        // Si préparation liée à une réservation en mode scan, restaurer son statut
        if ($preparation->mode === 'reservation' && $preparation->reservation_id) {
            Reservation::where('id', $preparation->reservation_id)
                ->where('status', 'en_preparation')
                ->update(['status' => 'approved']);
        }

        // Recalculer le statut des kits concernés
        foreach (array_unique($kitIds) as $kitId) {
            $this->recalculateKitStatus($kitId);
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENVOYER LA NOTIFICATION "EN COURS DE PRÉPARATION"
    // ─────────────────────────────────────────────────────────────────────

    public function sendNotification(Preparation $preparation)
    {
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation n\'est pas active.'], 422);
        }

        $preparation->load(['user', 'assets.category']);

        try {
            // Envoyer l'email
            Mail::to($preparation->user->email)->send(new PreparationReadyMail($preparation));

            // Envoyer la notification dans l'espace utilisateur
            $preparation->user->notify(new \App\Notifications\PreparationReadyNotification($preparation));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de l\'envoi : ' . $e->getMessage()], 500);
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCANNER UN ÉQUIPEMENT POUR UNE PRÉPARATION DE RÉSERVATION
    // ─────────────────────────────────────────────────────────────────────

    public function scanReservationAsset(Request $request, Preparation $preparation)
    {
        if ($preparation->mode !== 'reservation') {
            return response()->json(['error' => 'Cette préparation n\'est pas en mode réservation.'], 422);
        }
        if ($preparation->status !== 'preparing') {
            return response()->json(['error' => 'Cette préparation n\'est plus modifiable.'], 422);
        }

        $request->validate(['asset_tag' => 'required|string']);

        $asset = Asset::with('category:id,name')
            ->where('asset_tag', $request->asset_tag)
            ->first();

        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }

        $reservation = Reservation::with('assets')->findOrFail($preparation->reservation_id);

        // L'équipement doit faire partie de la réservation
        if (!$reservation->assets->contains('id', $asset->id)) {
            return response()->json(['error' => "Cet équipement ne fait pas partie de la réservation."], 422);
        }

        // Déjà scanné dans cette préparation ?
        if ($preparation->assets()->where('assets.id', $asset->id)->exists()) {
            return response()->json(['error' => "Cet équipement a déjà été mis en préparation.", 'already_scanned' => true], 422);
        }

        // Statut bloquant ?
        if (in_array($asset->status, ['retired', 'maintenance'])) {
            return response()->json(['error' => "Équipement indisponible (statut : {$asset->status})."], 422);
        }

        $previousStatus = $asset->status;

        PreparationAsset::create([
            'preparation_id'  => $preparation->id,
            'asset_id'        => $asset->id,
            'previous_status' => $previousStatus,
            'scanned_at'      => now(),
        ]);

        $asset->update(['status' => 'en_preparation']);

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'status_change',
            'notes'          => "Mis en préparation (réservation #{$reservation->id}) — Préparation #{$preparation->id}",
        ]);

        $scannedCount = $preparation->fresh()->assets()->count();
        $totalCount   = $reservation->assets->count();

        return response()->json([
            'ok'            => true,
            'asset'         => [
                'id'        => $asset->id,
                'name'      => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'status'    => 'en_preparation',
                'category'  => $asset->category?->name,
                'image'     => $this->imageUrl($asset->image),
            ],
            'scanned_count' => $scannedCount,
            'total_count'   => $totalCount,
            'all_scanned'   => $scannedCount >= $totalCount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Recalcule et met à jour le statut d'un kit selon ses équipements actifs.
     * Restaure 'available' si aucun équipement n'est en préparation ou sorti.
     */
    private function recalculateKitStatus(int $kitId): void
    {
        $kit = Kit::with('assets')->find($kitId);
        if (!$kit) return;

        $hasInPrep    = $kit->assets->contains(fn($a) => $a->status === 'en_preparation');
        $hasCheckedOut = $kit->assets->contains(fn($a) => $a->status === 'checked_out');

        if ($hasInPrep || $hasCheckedOut) {
            $kit->update(['status' => 'unavailable']);
        } else {
            // Aucun équipement bloquant → remettre disponible
            $kit->update(['status' => 'available']);
        }
    }

    private function imageUrl(?string $image): ?string
    {
        if (!$image) return null;
        if (str_starts_with($image, 'http')) return $image;
        return Storage::url($image);
    }

    private function formatReservationPreparation(Preparation $p): array
    {
        $scannedIds = $p->preparationAssets->pluck('asset_id')->toArray();

        $reservationAssets = $p->reservation?->assets->map(fn($a) => [
            'id'        => $a->id,
            'name'      => $a->name,
            'asset_tag' => $a->asset_tag,
            'status'    => $a->status,
            'category'  => $a->category?->name,
            'image'     => $this->imageUrl($a->image),
            'scanned'   => in_array($a->id, $scannedIds),
        ])->values()->all() ?? [];

        $totalCount   = count($reservationAssets);
        $scannedCount = count($scannedIds);

        return [
            'id'                  => $p->id,
            'title'               => $p->title,
            'mode'                => $p->mode,
            'status'              => $p->status,
            'planned_start_label' => $p->planned_start_at?->format('d/m/Y H:i'),
            'planned_end_label'   => $p->planned_end_at?->format('d/m/Y H:i'),
            'notes'               => $p->notes,
            'reservation_id'      => $p->reservation_id,
            'reservation'         => $p->reservation ? [
                'id'    => $p->reservation->id,
                'title' => $p->reservation->title,
                'status'=> $p->reservation->status,
            ] : null,
            'scanned_count'       => $scannedCount,
            'total_count'         => $totalCount,
            'all_scanned'         => $totalCount > 0 && $scannedCount >= $totalCount,
            'user'                => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'email' => $p->user->email] : null,
            'prepared_by'         => $p->preparedBy ? ['id' => $p->preparedBy->id, 'name' => $p->preparedBy->name] : null,
            'reservation_assets'  => $reservationAssets,
        ];
    }

    private function formatPreparation(Preparation $p): array
    {
        // Charger les pivot avec kit_id
        $p->loadMissing(['preparationAssets.kit']);

        // Construire un map asset_id → kit info depuis les pivots
        $kitByAsset = [];
        foreach ($p->preparationAssets as $pa) {
            if ($pa->kit_id) {
                $kitByAsset[$pa->asset_id] = [
                    'id'   => $pa->kit_id,
                    'name' => $pa->kit?->name ?? "Kit #{$pa->kit_id}",
                ];
            }
        }

        return [
            'id'                    => $p->id,
            'title'                 => $p->title,
            'status'                => $p->status,
            'planned_start_at'      => $p->planned_start_at?->format('Y-m-d\TH:i'),
            'planned_start_label'   => $p->planned_start_at?->format('d/m/Y H:i'),
            'planned_end_at'        => $p->planned_end_at?->format('Y-m-d\TH:i'),
            'planned_end_label'     => $p->planned_end_at?->format('d/m/Y H:i'),
            'auto_checkout_at'      => $p->auto_checkout_at?->format('Y-m-d\TH:i'),
            'auto_checkout_label'   => $p->auto_checkout_at?->format('d/m/Y H:i'),
            'notes'                 => $p->notes,
            'reservation_id'        => $p->reservation_id,
            'send_preparation_mail' => $p->send_preparation_mail,
            'send_checkout_mail'    => $p->send_checkout_mail,
            'validated_at'          => $p->validated_at?->format('d/m/Y H:i'),
            'created_at'            => $p->created_at?->format('d/m/Y H:i'),
            'user'                  => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name, 'email' => $p->user->email] : null,
            'prepared_by'           => $p->preparedBy ? ['id' => $p->preparedBy->id, 'name' => $p->preparedBy->name] : null,
            'validated_by'          => $p->validatedBy ? ['id' => $p->validatedBy->id, 'name' => $p->validatedBy->name] : null,
            'reservation'           => $p->reservation ? ['id' => $p->reservation->id, 'title' => $p->reservation->title, 'status' => $p->reservation->status] : null,
            'assets'                => $p->assets->map(fn($a) => [
                'id'              => $a->id,
                'name'            => $a->name,
                'asset_tag'       => $a->asset_tag,
                'status'          => $a->status,
                'category'        => $a->category?->name,
                'image'     => $this->imageUrl($a->image),
                'previous_status' => $a->pivot->previous_status,
                'scanned_at'      => \Carbon\Carbon::parse($a->pivot->scanned_at)->format('d/m/Y H:i'),
                'kit'             => $kitByAsset[$a->id] ?? null,
            ])->values()->all(),
        ];
    }
}
