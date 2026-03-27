<?php

namespace App\Http\Controllers;

use App\Mail\ReservationReturned;
use App\Models\Reservation;
use App\Models\Asset;
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
                    'status'       => $r->kit->status,
                    'status_label' => $r->kit->status_label,
                    'status_color' => $r->kit->status_color,
                ];
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
                'assets'            => $r->assets->map(fn($a) => [
                    'id'                  => $a->id,
                    'name'                => $a->name,
                    'asset_tag'           => $a->asset_tag,
                    'qr_code'             => $a->qr_code,
                    'returned_at'         => $a->pivot->returned_at,
                    'is_missing'          => (bool) $a->pivot->is_missing,
                    'returned_by_user_id' => $a->pivot->returned_by_user_id,
                    'in_kit'              => $r->kit_id !== null,
                ]),
            ];
        };

        // Réservations en retard
        $overdueReservations = Reservation::with(['user', 'assets', 'kit', 'returnedBy'])
            ->where('status', 'active')
            ->where('end_date', '<', $now)
            ->orderBy('end_date')
            ->get()
            ->map($mapReservation);

        // Réservations actives dans les délais
        $activeReservations = Reservation::with(['user', 'assets', 'kit', 'returnedBy'])
            ->where('status', 'active')
            ->where('end_date', '>=', $now)
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
                'kit'       => $r->kit ? ['id' => $r->kit->id, 'name' => $r->kit->name, 'status' => $r->kit->status] : null,
                'missing'   => $r->assets->filter(fn($a) => $a->pivot->is_missing)->map(fn($a) => [
                    'id' => $a->id, 'name' => $a->name, 'asset_tag' => $a->asset_tag,
                ])->values(),
                'return_at' => $r->return_at?->format('d/m/Y H:i'),
            ]);

        // Liste des utilisateurs pour le sélecteur "retourné par"
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Returns/Index', [
            'overdueReservations'        => $overdueReservations,
            'activeReservations'         => $activeReservations,
            'kitsAwaitingRegularization' => $kitsAwaitingRegularization,
            'users'                      => $users,
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
            'condition'           => 'nullable|in:good,damaged,lost',
            'good_condition'      => 'nullable|boolean',
            'return_notes'        => 'nullable|string',
            'returned_by_user_id' => 'nullable|exists:users,id',
        ]);

        // Priorité au champ condition (3 états) ; fallback sur good_condition (boolean)
        $conditionValue = $request->input('condition');
        if ($conditionValue === null) {
            $conditionValue = $request->input('good_condition', true) ? 'good' : 'damaged';
        }
        $goodCondition = $conditionValue === 'good';
        $isMissing     = $conditionValue === 'lost';

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }

        if ($asset->status !== 'checked_out') {
            return response()->json(['error' => "Cet équipement n'est pas en cours de sortie"], 422);
        }

        // Trouver la réservation où cet asset a été EFFECTIVEMENT sorti :
        // checkout_at défini + returned_at null → correspond au retour physique en attente.
        // On ne cherche PAS juste "la réservation active/approuvée la plus récente" car
        // depuis la v2, un équipement sorti peut avoir une réservation future approuvée en parallèle.
        $reservation = $asset->reservations()
            ->whereIn('reservations.status', ['active', 'approved'])
            ->wherePivotNotNull('checkout_at')
            ->wherePivotNull('returned_at')
            ->orderByDesc('reservations.start_date')
            ->first();

        if (!$reservation) {
            return response()->json(['error' => "Aucune réservation active trouvée avec cet équipement sorti"], 422);
        }

        if ($reservation->pivot->returned_at) {
            return response()->json(['error' => "Cet équipement a déjà été scanné pour le retour"], 409);
        }

        $returnedById = $request->input('returned_by_user_id') ?: null;
        $returnNotes  = $request->input('return_notes');

        $conditionLabel = match($conditionValue) {
            'good'    => 'Bon état',
            'damaged' => 'Endommagé',
            'lost'    => 'Perdu',
            default   => 'Bon état',
        };

        // Marquer le retour sur le pivot
        $reservation->assets()->updateExistingPivot($asset->id, [
            'returned_at'         => now(),
            'good_condition'      => $goodCondition,
            'is_missing'          => $isMissing,
            'return_notes'        => $returnNotes ?? ($isMissing ? 'Équipement déclaré perdu' : null),
            'returned_by_user_id' => $returnedById,
        ]);

        // Stocker le returner sur la réservation si non encore défini
        if ($returnedById && !$reservation->returned_by_user_id) {
            $reservation->update(['returned_by_user_id' => $returnedById]);
        }

        // Statut asset : perdu → maintenance, sinon disponible
        $newAssetStatus = $isMissing ? 'maintenance' : 'available';
        $asset->update([
            'status'                 => $newAssetStatus,
            'checked_out_to_user_id' => null,
            'expected_return_at'     => null,
        ]);

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

        // Vérifier si tous les assets (non manquants) sont retournés → marquer réservation comme retournée
        $reservation->refresh();
        $allAccountedFor = $reservation->assets->every(
            fn($a) => $a->pivot->returned_at !== null || $a->pivot->is_missing
        );
        if ($allAccountedFor) {
            $reservation->update(['status' => 'returned', 'return_at' => now()]);

            // Envoyer notification + email de confirmation de retour à l'utilisateur
            $reservation->user->notify(new ReservationReturnedNotification($reservation));
            try {
                Mail::to($reservation->user->email)->send(new ReservationReturned($reservation->fresh()));
            } catch (\Exception $e) {}
        }

        // Notifier la liste d'attente seulement si l'asset est redevenu disponible
        if (!$isMissing) {
            WaitlistController::notifyWaitlist($asset);
        }

        return response()->json([
            'success'       => true,
            'condition'     => $conditionValue,
            'asset'         => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
            'reservation'   => [
                'id'           => $reservation->id,
                'title'        => $reservation->title,
                'all_returned' => $reservation->fresh()->status === 'returned',
                'is_kit'       => $reservation->kit_id !== null,
            ],
        ]);
    }

    public function sendReminder(Request $request, Reservation $reservation)
    {
        $reservation->user->notify(new OverdueReminderNotification($reservation));
        return back()->with('success', "Rappel envoyé à {$reservation->user->name}.");
    }
}
