<?php

namespace App\Http\Controllers;

use App\Mail\OverdueReminderMail;
use App\Mail\ReservationReturned;
use App\Models\Reservation;
use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\AssetMovement;
use App\Models\Kit;
use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\OverdueReminderNotification;
use App\Notifications\ReservationReturnedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use App\Http\Controllers\WaitlistController;

class ReturnController extends Controller
{
    public function index()
    {
        $now = now();

        $mapReservation = function ($r) use ($now) {
            $endDate  = \Carbon\Carbon::parse($r->end_date);
            $isOverdue = $endDate->lt($now);

            $kitData = null;
            if ($r->kit_id && $r->kit) {
                $kitData = [
                    'id'           => $r->kit->id,
                    'name'         => $r->kit->name,
                    'status'       => $r->kit->current_status,
                    'status_label' => $r->kit->status_label,
                    'status_color' => $r->kit->status_color,
                ];
            }

            // Collecter les réservations enfants si c'est une réservation parente
            $childReservations = [];
            if ($r->childReservations && $r->childReservations->isNotEmpty()) {
                $childReservations = $r->childReservations->map(function($child) use ($now) {
                    $childEndDate = \Carbon\Carbon::parse($child->end_date);
                    $childIsOverdue = $childEndDate->lt($now);

                    return [
                        'id'             => $child->id,
                        'title'          => $child->title,
                        'start_date'     => $child->start_date,
                        'end_date'       => $child->end_date,
                        'days_overdue'   => $childIsOverdue ? (int) $now->diffInDays($childEndDate) : 0,
                        'days_remaining' => !$childIsOverdue ? (int) $now->diffInDays($childEndDate) : 0,
                    ];
                })->toArray();
            }

            // Collecter tous les équipements (parent + enfants)
            $allAssets = collect($r->assets);
            if ($r->childReservations && $r->childReservations->isNotEmpty()) {
                foreach ($r->childReservations as $child) {
                    $allAssets = $allAssets->merge($child->assets);
                }
            }

            return [
                'id'                => $r->id,
                'title'             => $r->title,
                'kit'               => $kitData,
                'has_missing_items' => (bool) $r->has_missing_items,
                'user'              => [
                    'id'    => $r->user->id,
                    'name'  => $r->user->name,
                    'email' => $r->user->email,
                ],
                'returned_by'       => $r->returnedBy ? ['id' => $r->returnedBy->id, 'name' => $r->returnedBy->name] : null,
                'start_date'        => $r->start_date,
                'end_date'          => $r->end_date,
                'days_overdue'      => $isOverdue ? (int) $now->diffInDays($endDate) : 0,
                'days_remaining'    => !$isOverdue ? (int) $now->diffInDays($endDate) : 0,
                'child_reservations' => $childReservations,
                'assets'            => $allAssets->map(fn($a) => [
                    'id'                    => $a->id,
                    'name'                  => $a->name,
                    'asset_tag'             => $a->asset_tag,
                    'qr_code'               => $a->qr_code,
                    'has_scratch'           => (bool) $a->has_scratch,
                    'has_belge'             => (bool) $a->has_belge,
                    'return_check_note'     => $a->return_check_note,
                    'returned_at'           => $a->pivot->returned_at,
                    'is_missing'            => (bool) $a->pivot->is_missing,
                    'returned_by_user_id'   => $a->pivot->returned_by_user_id,
                    'pending_verification'  => (bool) $a->pivot->pending_verification,
                    'verification_note'     => $a->pivot->verification_note,
                    'in_kit'                => $r->kit_id !== null,
                ]),
            ];
        };

        // Réservations en retard (exclure les enfants, inclure les relations)
        $overdueReservations = Reservation::with(['user', 'assets', 'kit', 'returnedBy', 'childReservations.assets'])
            ->where('status', 'active')
            ->where('end_date', '<', $now)
            ->whereNull('parent_reservation_id') // Ne récupérer que les parentes et les réservations non associées
            ->orderBy('end_date')
            ->get()
            ->map($mapReservation);

        // Réservations actives dans les délais (exclure les enfants, inclure les relations)
        $activeReservations = Reservation::with(['user', 'assets', 'kit', 'returnedBy', 'childReservations.assets'])
            ->where('status', 'active')
            ->where('end_date', '>=', $now)
            ->whereNull('parent_reservation_id') // Ne récupérer que les parentes et les réservations non associées
            ->orderBy('end_date')
            ->get()
            ->map($mapReservation);

        // Kits en attente de régularisation (retournés mais avec éléments manquants)
        $kitsAwaitingRegularization = Reservation::with(['user', 'kit', 'assets'])
            ->where('status', 'returned')
            ->where('has_missing_items', true)
            ->whereNotNull('kit_id')
            ->orderByDesc('return_at')
            ->get()
            ->map(fn($r) => [
                'id'        => $r->id,
                'title'     => $r->title,
                'user'      => ['id' => $r->user->id, 'name' => $r->user->name],
                'kit'       => $r->kit ? ['id' => $r->kit->id, 'name' => $r->kit->name, 'status' => $r->kit->current_status] : null,
                'missing'   => $r->assets->filter(fn($a) => $a->pivot->is_missing)->map(fn($a) => [
                    'id' => $a->id, 'name' => $a->name, 'asset_tag' => $a->asset_tag,
                ])->values(),
                'return_at' => $r->return_at?->format('d/m/Y H:i'),
            ]);

        // Liste des utilisateurs pour le sélecteur "retourné par"
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        // Lieux de stockage pour le retour d'équipements affectés
        $storagePlaces = \App\Models\Place::where('is_storage', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'color' => $p->color ?? '#6B7280',
            ]);

        // Équipements affectés à un lieu et non encore retournés
        // Exclure les affectations permanentes (sans date de retour prévue)
        $placeAssignments = \App\Models\AssetPlaceAssignment::with(['asset', 'place'])
            ->whereNull('returned_at')
            ->whereNotNull('return_expected_at')  // Uniquement affectations temporaires
            ->orderBy('place_id')
            ->orderBy('assigned_at')
            ->get()
            ->groupBy('place_id')
            ->map(fn($assignments) => [
                'place' => [
                    'id'         => $assignments->first()->place->id,
                    'name'       => $assignments->first()->place->name,
                    'color'      => $assignments->first()->place->color,
                    'is_storage' => (bool) $assignments->first()->place->is_storage,
                ],
                'assignments' => $assignments->map(fn($a) => [
                    'id'                 => $a->id,
                    'asset'              => [
                        'id'        => $a->asset->id,
                        'name'      => $a->asset->name,
                        'asset_tag' => $a->asset->asset_tag,
                    ],
                    'assigned_at'        => $a->assigned_at?->format('d/m/Y'),
                    'return_expected_at' => $a->return_expected_at?->format('d/m/Y'),
                    'is_overdue'         => $a->return_expected_at && $a->return_expected_at->lt($now),
                ])->values(),
            ])->values();

        return Inertia::render('Returns/Index', [
            'overdueReservations'        => $overdueReservations,
            'activeReservations'         => $activeReservations,
            'kitsAwaitingRegularization' => $kitsAwaitingRegularization,
            'users'                      => $users,
            'placeAssignments'           => $placeAssignments,
            'storagePlaces'              => $storagePlaces,
        ]);
    }

    /**
     * Scan d'un retour par code barre USB.
     * Accepte optionnellement :
     * - returned_by_user_id : l'utilisateur qui a rapporté l'équipement (si différent du réservant)
     */
    public function scanReturn(Request $request)
    {
        $request->validate([
            'asset_tag'           => 'required|string',
            'condition'           => 'nullable|in:good,damaged,lost,pending',
            'good_condition'      => 'nullable|boolean',
            'return_notes'        => 'nullable|string',
            'returned_by_user_id' => 'nullable|exists:users,id',
            'scratch_lost'        => 'nullable|boolean',
            'belge_lost'          => 'nullable|boolean',
            'storage_place_id'    => 'nullable|exists:places,id',
        ]);

        // Priorité au champ condition (4 états) ; fallback sur good_condition (boolean)
        $conditionValue = $request->input('condition');
        if ($conditionValue === null) {
            $conditionValue = $request->input('good_condition', true) ? 'good' : 'damaged';
        }
        $isPending     = $conditionValue === 'pending';
        $goodCondition = $conditionValue === 'good';
        $isMissing     = $conditionValue === 'lost';

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }

        // Source de vérité : le pivot de réservation, pas le champ status de l'asset
        // (qui peut être désynchronisé suite à un retrait sans scan, import, modif manuelle…).
        //
        // Deux cas :
        //  - Réservation "active"   → l'équipement est sorti même si checkout_at est null
        //    (retrait via bouton "Retrait" qui n'écrivait pas checkout_at avant le correctif)
        //  - Réservation "approved" → seuls les assets effectivement scannés (checkout_at non null)
        //    sont considérés comme sortis
        $reservation = $asset->reservations()
            ->where(function ($q) {
                $q->where('reservations.status', 'active')
                  ->orWhere(function ($q2) {
                      $q2->where('reservations.status', 'approved')
                         ->wherePivotNotNull('checkout_at');
                  });
            })
            ->wherePivotNull('returned_at')
            ->orderByDesc('reservations.start_date')
            ->first();

        if (!$reservation) {
            // Vérifier si l'équipement est affecté à un lieu (retour depuis la page retours classique)
            $placeAssignment = \App\Models\AssetPlaceAssignment::where('asset_id', $asset->id)
                ->whereNull('returned_at')
                ->with('place')
                ->latest('assigned_at')
                ->first();

            if ($placeAssignment) {
                $storagePlaceId = $request->input('storage_place_id');

                // Si aucun lieu de stockage n'est fourni, afficher le modal SANS marquer le retour
                if (!$storagePlaceId) {
                    return response()->json([
                        'success'              => true,
                        'needs_storage_place'  => true,
                        'asset'                => [
                            'id'        => $asset->id,
                            'name'      => $asset->name,
                            'asset_tag' => $asset->asset_tag,
                        ],
                        'from_place'           => [
                            'name'  => $placeAssignment->place->name,
                            'color' => $placeAssignment->place->color,
                        ],
                    ]);
                }

                // Maintenant on a le lieu de stockage, on peut marquer le retour
                $placeAssignment->update(['returned_at' => now()]);

                // Mettre à jour le lieu de stockage de l'asset
                $storagePlace = \App\Models\Place::find($storagePlaceId);
                if ($storagePlace) {
                    $asset->update([
                        'place_id' => $storagePlaceId,
                        'location' => $storagePlace->name,  // Mettre à jour aussi le champ texte "location"
                    ]);
                    \App\Models\AssetMovement::create([
                        'asset_id' => $asset->id,
                        'user_id'  => Auth::id(),
                        'action'   => 'place_returned',
                        'notes'    => "Retour depuis « {$placeAssignment->place->name} » → stocké au « {$storagePlace->name} »",
                    ]);
                }

                // Mise à jour automatique du statut : Sorti → Disponible
                if ($asset->status === 'checked_out') {
                    $asset->update(['status' => 'available']);
                }

                return response()->json([
                    'success'        => true,
                    'place_return'   => true,
                    'asset'          => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
                    'from_place'     => ['name' => $placeAssignment->place->name, 'color' => $placeAssignment->place->color],
                    'storage_place'  => ['name' => $storagePlace->name, 'color' => $storagePlace->color],
                    'message'        => "✓ {$asset->name} retourné depuis « {$placeAssignment->place->name} » → stocké au « {$storagePlace->name} »",
                ]);
            }

            return response()->json(['error' => "Cet équipement n'est pas en cours de sortie"], 422);
        }

        $returnedById = $request->input('returned_by_user_id') ?: null;
        $returnNotes  = $request->input('return_notes');

        // Accessoires manquants déclarés perdus par l'admin (scratch / belge)
        $scratchLost = $request->boolean('scratch_lost');
        $belgeLost   = $request->boolean('belge_lost');
        $accessoryNotes = [];
        if ($scratchLost) $accessoryNotes[] = 'Velcro/scratch manquant (déclaré perdu)';
        if ($belgeLost)   $accessoryNotes[] = 'Belge manquant (déclaré perdu)';
        if (!empty($accessoryNotes)) {
            $lostStr     = implode(', ', $accessoryNotes);
            $returnNotes = $returnNotes ? "{$lostStr} — {$returnNotes}" : $lostStr;
        }

        // ── Cas "À vérifier" : mise en attente, pas de retour définitif ──────────
        if ($isPending) {
            if (!$returnNotes) {
                return response()->json(['error' => 'Une note est obligatoire pour mettre un équipement en attente de vérification.'], 422);
            }
            $reservation->assets()->updateExistingPivot($asset->id, [
                'pending_verification' => true,
                'verification_note'    => $returnNotes,
                'returned_by_user_id'  => $returnedById,
            ]);
            AssetMovement::create([
                'asset_id'       => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id'        => Auth::id(),
                'action'         => 'return',
                'notes'          => "Mis en attente de vérification — {$returnNotes}",
            ]);
            return response()->json([
                'success'   => true,
                'pending'   => true,
                'condition' => 'pending',
                'asset'     => [
                    'name'              => $asset->name,
                    'asset_tag'         => $asset->asset_tag,
                    'return_check_note' => $asset->return_check_note,
                ],
                'reservation' => [
                    'id'           => $reservation->id,
                    'title'        => $reservation->title,
                    'all_returned' => false,
                    'is_kit'       => $reservation->kit_id !== null,
                ],
            ]);
        }

        // ── Retour normal (good / damaged / lost) ─────────────────────────────────
        $conditionLabel = match($conditionValue) {
            'good'    => 'Bon état',
            'damaged' => 'Endommagé',
            'lost'    => 'Perdu',
            default   => 'Bon état',
        };

        // Marquer le retour sur le pivot (efface aussi l'éventuel pending_verification)
        $reservation->assets()->updateExistingPivot($asset->id, [
            'returned_at'          => now(),
            'good_condition'       => $goodCondition,
            'is_missing'           => $isMissing,
            'return_notes'         => $returnNotes ?? ($isMissing ? 'Équipement déclaré perdu' : null),
            'returned_by_user_id'  => $returnedById,
            'pending_verification' => false,
            'verification_note'    => null,
        ]);

        // Stocker le returner sur la réservation si non encore défini
        if ($returnedById && !$reservation->returned_by_user_id) {
            $reservation->update(['returned_by_user_id' => $returnedById]);
        }

        // Statut asset : perdu → maintenance, sinon disponible
        $newAssetStatus = $isMissing ? 'maintenance' : 'available';
        $assetUpdateData = [
            'status'                 => $newAssetStatus,
            'checked_out_to_user_id' => null,
            'expected_return_at'     => null,
        ];
        // Décocher automatiquement les accessoires déclarés perdus
        if ($scratchLost) $assetUpdateData['has_scratch'] = false;
        if ($belgeLost)   $assetUpdateData['has_belge']   = false;
        $asset->update($assetUpdateData);

        // Si endommagé → créer fiche maintenance automatique
        if ($conditionValue === 'damaged') {
            Maintenance::create([
                'asset_id'    => $asset->id,
                'created_by'  => Auth::id(),
                'title'       => "Retour endommagé — {$asset->name}",
                'description' => $returnNotes ?: "Équipement retourné endommagé lors de la réservation #{$reservation->id}.",
                'started_at'  => now()->toDateString(),
                'status'      => 'in_progress',
            ]);
        }

        // Si perdu → créer fiche maintenance pour traçabilité
        if ($isMissing) {
            Maintenance::create([
                'asset_id'    => $asset->id,
                'created_by'  => Auth::id(),
                'title'       => "Équipement perdu — {$asset->name}",
                'description' => $returnNotes ?: "Équipement déclaré perdu lors de la réservation #{$reservation->id}.",
                'started_at'  => now()->toDateString(),
                'status'      => 'in_progress',
            ]);
        }

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'return',
            'notes'          => "Retour — État : {$conditionLabel}" . ($returnNotes ? " — {$returnNotes}" : ''),
        ]);

        // Récupérer les composants de l'équipement pour la vérification post-retour
        // Exclure les composants marqués comme "missing" (manquants) car ils ne doivent pas être vérifiés
        $components = $asset->components()
            ->where(function($query) {
                $query->where('condition', '!=', 'missing')
                      ->orWhereNull('condition');
            })
            ->get()
            ->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'quantity'    => $c->quantity,
            ])
            ->values();

        // Si l'équipement a des composants, on reporte la clôture de la réservation
        // à après la vérification des composants (via recordComponentCheck).
        // Sinon, on clôt la réservation immédiatement si tous les assets sont rendus.
        $allReturned = false;
        if ($components->isEmpty()) {
            $reservation->refresh();
            $allAccountedFor = $reservation->assets->every(
                fn($a) => $a->pivot->returned_at !== null || $a->pivot->is_missing
            );
            if ($allAccountedFor) {
                $reservation->update(['status' => 'returned', 'return_at' => now()]);
                $this->resetKitStatusOnReturn($reservation->fresh());
                $reservation->user->notify(new ReservationReturnedNotification($reservation));
                $alertEnabled = \App\Models\Setting::get('alert_reservation_returned_enabled', 'true') === 'true';
                $alertChannel = \App\Models\Setting::get('alert_reservation_returned_channel', 'notification');
                if ($alertEnabled && in_array($alertChannel, ['email', 'both'])) {
                    try {
                        Mail::to($reservation->user->email)->send(new ReservationReturned($reservation->fresh()));
                    } catch (\Exception $e) {}
                }
                $allReturned = true;
            }
        }

        // Notifier la liste d'attente seulement si l'asset est redevenu disponible
        if (!$isMissing) {
            WaitlistController::notifyWaitlist($asset);
        }

        return response()->json([
            'success'           => true,
            'condition'         => $conditionValue,
            'asset'             => [
                'id'                => $asset->id,
                'name'              => $asset->name,
                'asset_tag'         => $asset->asset_tag,
                'return_check_note' => $asset->return_check_note,
            ],
            'reservation'       => [
                'id'           => $reservation->id,
                'title'        => $reservation->title,
                'all_returned' => $allReturned,
                'is_kit'       => $reservation->kit_id !== null,
            ],
            'components'        => $components,
            'has_belge'         => (bool) $asset->has_belge,
            'has_scratch'       => (bool) $asset->has_scratch,
        ]);
    }

    /**
     * Enregistrer le résultat de la vérification des composants après un retour.
     * Appelé par le frontend à la fin du parcours composant par composant.
     */
    public function recordComponentCheck(Request $request)
    {
        $request->validate([
            'reservation_id'       => 'required|exists:reservations,id',
            'asset_id'             => 'required|exists:assets,id',
            'missing_component_ids' => 'nullable|array',
            'missing_component_ids.*' => 'integer|exists:asset_components,id',
        ]);

        $reservation = Reservation::findOrFail($request->reservation_id);
        $asset       = Asset::findOrFail($request->asset_id);
        $missingIds  = $request->input('missing_component_ids', []);

        // Mettre à jour le pivot reservation_assets avec les composants manquants
        $reservation->assets()->updateExistingPivot($asset->id, [
            'missing_component_ids' => empty($missingIds) ? null : json_encode($missingIds),
        ]);

        // Si des composants sont manquants → ajouter une note de mouvement
        if (!empty($missingIds)) {
            $missingNames = AssetComponent::whereIn('id', $missingIds)->pluck('name')->join(', ');
            AssetMovement::create([
                'asset_id'       => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id'        => Auth::id(),
                'action'         => 'return',
                'notes'          => "Composants manquants au retour : {$missingNames}",
            ]);
        }

        // Vérifier si tous les assets sont maintenant retournés → clore la réservation
        $reservation->refresh();
        $allAccountedFor = $reservation->assets->every(
            fn($a) => $a->pivot->returned_at !== null || $a->pivot->is_missing
        );
        $allReturned = false;
        if ($allAccountedFor && $reservation->status !== 'returned') {
            $reservation->update(['status' => 'returned', 'return_at' => now()]);
            $this->resetKitStatusOnReturn($reservation->fresh());
            $reservation->user->notify(new ReservationReturnedNotification($reservation));
            $alertEnabled = \App\Models\Setting::get('alert_reservation_returned_enabled', 'true') === 'true';
            $alertChannel = \App\Models\Setting::get('alert_reservation_returned_channel', 'notification');
            if ($alertEnabled && in_array($alertChannel, ['email', 'both'])) {
                try {
                    Mail::to($reservation->user->email)->send(new ReservationReturned($reservation->fresh()));
                } catch (\Exception $e) {}
            }
            $allReturned = true;
        }

        return response()->json([
            'ok'               => true,
            'missing_count'    => count($missingIds),
            'all_returned'     => $allReturned,
            'reservation_title' => $reservation->title,
            'is_kit'           => $reservation->kit_id !== null,
        ]);
    }

    /**
     * Annule un retour effectué dans les 5 dernières minutes.
     * Utile en cas de scan accidentel d'un équipement pendant la vérification.
     */
    public function undoReturn(Reservation $reservation, Asset $asset)
    {
        $reservationAsset = $reservation->assets()->where('assets.id', $asset->id)->first();

        if (!$reservationAsset || !$reservationAsset->pivot->returned_at) {
            return response()->json(['error' => "Cet équipement n'a pas encore été retourné."], 422);
        }

        $returnedAt = \Carbon\Carbon::parse($reservationAsset->pivot->returned_at);

        if ($returnedAt->diffInMinutes(now()) > 5) {
            return response()->json(['error' => 'Le délai d\'annulation de 5 minutes est dépassé.'], 422);
        }

        // Réinitialiser le pivot de réservation
        $reservation->assets()->updateExistingPivot($asset->id, [
            'returned_at'          => null,
            'good_condition'       => null,
            'is_missing'           => false,
            'return_notes'         => null,
            'returned_by_user_id'  => null,
            'pending_verification' => false,
            'verification_note'    => null,
        ]);

        // Restaurer le statut de l'équipement
        $asset->update([
            'status'                 => 'checked_out',
            'checked_out_to_user_id' => $reservation->user_id,
            'expected_return_at'     => $reservation->end_date,
        ]);

        // Si la réservation était passée en "retourné" → la remettre active
        if ($reservation->status === 'returned') {
            $reservation->update(['status' => 'active', 'return_at' => null]);
        }

        // Supprimer les fiches maintenance créées automatiquement lors de ce retour
        Maintenance::where('asset_id', $asset->id)
            ->where('created_at', '>=', $returnedAt->copy()->subSeconds(15))
            ->where('created_at', '<=', $returnedAt->copy()->addSeconds(15))
            ->whereIn('title', [
                "Retour endommagé — {$asset->name}",
                "Équipement perdu — {$asset->name}",
            ])
            ->delete();

        // Supprimer le mouvement d'asset créé lors du retour
        AssetMovement::where('asset_id', $asset->id)
            ->where('reservation_id', $reservation->id)
            ->where('action', 'return')
            ->where('created_at', '>=', $returnedAt->copy()->subSeconds(15))
            ->delete();

        return response()->json([
            'success' => true,
            'asset'   => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
        ]);
    }

    public function sendReminder(Request $request, Reservation $reservation)
    {
        try {
            Mail::to($reservation->user->email)->send(new OverdueReminderMail($reservation));
        } catch (\Exception $e) {
            return back()->withErrors(['reminder' => 'Échec de l\'envoi : ' . $e->getMessage()]);
        }
        return back()->with('success', "Rappel email envoyé à {$reservation->user->name}.");
    }

    /**
     * Recalcule le statut de tous les kits liés à une réservation après un retour.
     * Un kit redevient 'available' quand tous ses équipements sont retournés.
     */
    private function resetKitStatusOnReturn(Reservation $reservation): void
    {
        // Utiliser la méthode updateKitStatuses() pour cohérence avec le reste du workflow
        // Quand la réservation est retournée, les kits complets redeviennent 'available'
        $reservation->updateKitStatuses('available');
    }
}
