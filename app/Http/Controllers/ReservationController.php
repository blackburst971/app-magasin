<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Kit;
use App\Models\Reservation;
use App\Models\ReservationAsset;
use App\Models\AssetMovement;
use App\Models\CheckoutForm;
use App\Models\Setting;
use App\Models\User;
use App\Mail\ReservationPending;
use App\Mail\ReservationConfirmed;
use App\Mail\ReservationRejected;
use App\Mail\CheckoutSummary;
use App\Mail\ReservationAwaitingSupervisor;
use App\Mail\ReservationReturned;
use App\Mail\ReservationCancelledByAdmin;
use App\Http\Controllers\WaitlistController;
use App\Models\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');

        // Standard users (non-admin) see only their own reservations
        if (!$isAdmin) {
            $reservations = Reservation::with(['user', 'assets'])
                ->where('user_id', $user->id)
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->latest()
                ->paginate(15)
                ->withQueryString()
                ->through(fn($r) => [
                    'id'           => $r->id,
                    'title'        => $r->title,
                    'start_date'   => $r->start_date->format('d/m/Y H:i'),
                    'end_date'     => $r->end_date->format('d/m/Y H:i'),
                    'status'       => $r->status,
                    'status_label' => $r->status_label,
                    'status_color' => $r->status_color,
                    'assets_count' => $r->assets->count(),
                ]);

            return Inertia::render('Reservations/Index', [
                'reservations' => $reservations,
                'filters'      => $request->only('status'),
                'isAdmin'      => false,
            ]);
        }

        $query = Reservation::with(['user', 'assets'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            // Par défaut, l'admin ne voit pas les réservations en attente de validation superviseur
            // (elles sont gérées dans l'espace du superviseur)
            ->when(!$request->status, fn($q) => $q->where('status', '!=', 'awaiting_supervisor'))
            ->latest();

        $reservations = $query->paginate(15)->withQueryString()
            ->through(fn($r) => [
                'id'                    => $r->id,
                'title'                 => $r->title,
                'user'                  => $r->user->name,
                'start_date'            => $r->start_date->format('d/m/Y H:i'),
                'end_date'              => $r->end_date->format('d/m/Y H:i'),
                'status'                => $r->status,
                'status_label'          => $r->status_label,
                'status_color'          => $r->status_color,
                'assets_count'          => $r->assets->count(),
                'parent_reservation_id' => $r->parent_reservation_id,
            ]);

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'filters'      => $request->only('status'),
            'isAdmin'      => $isAdmin,
            'users'        => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function create(Request $request): Response
    {
        $requiredForm = CheckoutForm::where('is_active', true)
            ->where('is_required_for_reservation', true)
            ->with('fields')
            ->first();

        $examMode = Setting::get('exam_mode', 'false') === 'true';

        // Récupérer tous les superviseurs de l'utilisateur connecté
        $userSupervisors = $request->user()->getAllSupervisors()->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'email' => $s->email,
        ])->values();

        // Superviseur principal (par défaut)
        $principalSupervisorId = $request->user()->supervisor_id;

        // Liste des utilisateurs disponibles pour les participants
        $currentUser = $request->user();
        $availableUsers = collect();

        // Étudiants : peuvent choisir uniquement d'autres étudiants
        if ($currentUser->hasRole('student')) {
            $availableUsers = \App\Models\User::role('student')
                ->where('id', '!=', $currentUser->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
        }
        // Professeurs : peuvent choisir étudiants ET professeurs
        elseif ($currentUser->hasRole('professeur')) {
            $availableUsers = \App\Models\User::whereHas('roles', function($q) {
                $q->whereIn('name', ['student', 'professeur']);
            })
            ->where('id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        }
        // Personnel et autres : pas de participants pour le moment
        else {
            $availableUsers = collect();
        }

        return Inertia::render('Reservations/Create', [
            'requiredForm'            => $requiredForm,
            'examMode'                => $examMode,
            'userSupervisors'         => $userSupervisors,
            'principalSupervisorId'   => $principalSupervisorId,
            'examAdvanceHours'        => (int) Setting::get('exam_advance_hours', 24),
            'examProcessStart'        => (int) Setting::get('exam_process_start', 7),
            'examProcessEnd'          => (int) Setting::get('exam_process_end', 9),
            'availableParticipants'   => $availableUsers,
        ]);
    }

    public function store(Request $request)
    {
        $isAdmin = $request->user()->hasRole('admin');

        $rules = [
            'title'           => 'required|string|max:255',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'asset_ids'       => 'nullable|array',
            'asset_ids.*'     => 'exists:assets,id',
            'kit_ids'         => 'nullable|array',
            'kit_ids.*'       => 'exists:kits,id',
            'notes'           => 'nullable|string|max:1000',
            'form_id'         => 'nullable|exists:checkout_forms,id',
            'form_responses'  => 'nullable|array',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ];
        if ($isAdmin) {
            $rules['user_id'] = 'nullable|exists:users,id';
            $rules['status']  = 'nullable|in:pending,approved,active,cancelled,rejected';
        }

        // Non-admin: if a required form exists, ensure responses are submitted
        if (!$isAdmin && $request->form_id) {
            $requiredForm = CheckoutForm::where('id', $request->form_id)
                ->where('is_required_for_reservation', true)
                ->first();
            if ($requiredForm && empty($request->form_responses)) {
                return back()->withErrors(['form_responses' => 'Vous devez remplir le formulaire de sortie avant de soumettre.']);
            }
        }

        $data = $request->validate($rules, [
            'title.required'      => 'Le titre de la réservation est obligatoire.',
            'start_date.required' => 'La date de début est obligatoire.',
            'end_date.required'   => 'La date de fin est obligatoire.',
            'end_date.after'      => 'La date de fin doit être postérieure à la date de début.',
        ]);

        // Au moins un équipement individuel ou un kit est requis
        $assetIds = array_filter($data['asset_ids'] ?? []);
        $kitIds   = array_filter($data['kit_ids']   ?? []);
        if (empty($assetIds) && empty($kitIds)) {
            return back()->withErrors(['asset_ids' => 'Veuillez sélectionner au moins un équipement ou un kit.']);
        }

        // ── Mode examen : vérification délai d'avance ──────────────────────────
        if (!$isAdmin && Setting::get('exam_mode', 'false') === 'true') {
            $advanceHours = (int) Setting::get('exam_advance_hours', 24);
            $minStart     = now()->addHours($advanceHours);
            $requestedStart = new \DateTime($data['start_date']);
            if ($requestedStart < $minStart) {
                return back()->withErrors([
                    'start_date' => "En mode examen, les réservations doivent être effectuées au moins {$advanceHours}h à l'avance (début au plus tôt : " . $minStart->format('d/m/Y H:i') . ').',
                ]);
            }
        }


        // Check availability
        $start         = new \DateTime($data['start_date']);
        $end           = new \DateTime($data['end_date']);
        $hardConflicts = [];

        foreach ($assetIds as $assetId) {
            $asset = Asset::findOrFail($assetId);
            $avail = $asset->getAvailabilityForPeriod($start, $end);
            if ($avail['status'] === 'unavailable') {
                $hardConflicts[] = $asset->name . ' (' . $avail['reason'] . ')';
            }
        }

        // Vérifier la disponibilité des assets des kits
        $kitsToAttach = [];
        foreach ($kitIds as $kitId) {
            $kit = Kit::with('assets')->findOrFail($kitId);
            if (!$kit->is_reservable || $kit->status === 'unavailable') {
                return back()->withErrors(['kit_ids' => "Le kit « {$kit->name} » n'est pas disponible à la réservation."]);
            }
            foreach ($kit->assets as $asset) {
                if (!in_array($asset->id, $assetIds)) { // éviter les doublons avec assets individuels
                    $avail = $asset->getAvailabilityForPeriod($start, $end);
                    if ($avail['status'] === 'unavailable') {
                        $hardConflicts[] = "Kit « {$kit->name} » → {$asset->name} ({$avail['reason']})";
                    }
                }
            }
            $kitsToAttach[] = $kit;
        }

        if (!empty($hardConflicts)) {
            return back()->withErrors([
                'asset_ids' => 'Ces équipements ne sont pas disponibles : ' . implode(', ', $hardConflicts),
            ]);
        }

        // Determine user and initial status
        if ($isAdmin && !empty($data['user_id'])) {
            $targetUserId = $data['user_id'];
            $targetUser   = User::find($targetUserId);
        } else {
            $targetUserId = $request->user()->id;
            $targetUser   = $request->user();
        }

        if ($isAdmin && !empty($data['status'])) {
            $initialStatus = $data['status'];
        } else {
            // Les rôles avec bypass_supervisor_approval vont directement en pending
            $bypassSupervisor = $targetUser->hasPermissionTo('bypass_supervisor_approval');
            if ($bypassSupervisor) {
                $initialStatus = 'pending';
            } else {
                $supervisor    = $targetUser->getSupervisor();
                $initialStatus = $supervisor ? 'awaiting_supervisor' : 'pending';
            }
        }

        // Construire le titre auto si besoin (panier mixte)
        $autoTitle = $data['title'];

        // Pour rétrocompat : si 1 seul kit sans assets individuels, on garde kit_id
        $singleKitId = (count($kitsToAttach) === 1 && empty($assetIds))
            ? $kitsToAttach[0]->id
            : null;

        $reservation = Reservation::create([
            'user_id'              => $targetUserId,
            'kit_id'               => $singleKitId,
            'title'                => $autoTitle,
            'start_date'           => $data['start_date'],
            'end_date'             => $data['end_date'],
            'notes'                => $data['notes'] ?? null,
            'status'               => $initialStatus,
            'supervisor_chosen_id' => $data['supervisor_chosen_id'] ?? null,
        ]);

        // Attacher les assets individuels
        if (!empty($assetIds)) {
            $reservation->assets()->attach($assetIds);

            // Auto-inclure les accessoires liés (parent_asset_id)
            $accessoryIds = [];
            foreach ($assetIds as $assetId) {
                $accessories = Asset::where('parent_asset_id', $assetId)
                    ->whereNotIn('id', array_merge($assetIds, $accessoryIds))
                    ->get();
                foreach ($accessories as $acc) {
                    $avail = $acc->getAvailabilityForPeriod($start, $end);
                    if ($avail['status'] !== 'unavailable') {
                        $accessoryIds[] = $acc->id;
                    }
                }
            }
            if (!empty($accessoryIds)) {
                $reservation->assets()->attach($accessoryIds);
            }
        }

        // Attacher les assets des kits (sans doublons) + pivoter reservation_kits
        foreach ($kitsToAttach as $kit) {
            $kitAssetIds = $kit->assets->pluck('id')
                ->diff($assetIds) // exclure les assets déjà attachés individuellement
                ->values()->toArray();
            if (!empty($kitAssetIds)) {
                $reservation->assets()->attach($kitAssetIds);
            }
            $reservation->kits()->attach($kit->id);
        }

        // Save form responses if submitted with reservation
        if (!empty($data['form_id']) && !empty($data['form_responses'])) {
            \App\Models\CheckoutFormResponse::create([
                'checkout_form_id' => $data['form_id'],
                'reservation_id'   => $reservation->id,
                'user_id'          => $targetUserId,
                'responses'        => $data['form_responses'],
                'submitted_at'     => now(),
            ]);
        }

        // If admin sets status to approved, record the approval
        if ($isAdmin && $initialStatus === 'approved') {
            $reservation->update([
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            try { Mail::to($reservation->user->email)->send(new ReservationConfirmed(
                    $reservation,
                    Setting::get('auto_cancel_enabled', 'false') === 'true',
                    (int) Setting::get('auto_cancel_hours', '24')
                )); } catch (\Exception $e) {}
        }

        // Standard user flow: notifications
        if (!$isAdmin) {
            $bypassSupervisor = $targetUser->hasPermissionTo('bypass_supervisor_approval');
            if (!$bypassSupervisor) {
                // Utiliser le superviseur choisi si spécifié, sinon fallback sur getSupervisor()
                $supervisor = null;
                if (!empty($data['supervisor_chosen_id'])) {
                    $supervisor = User::find($data['supervisor_chosen_id']);
                } else {
                    $supervisor = $targetUser->getSupervisor();
                }

                if ($supervisor) {
                    $supervisor->notify(new \App\Notifications\ReservationAwaitingSupervisorNotification($reservation));
                    try { Mail::to($supervisor->email)->send(new ReservationAwaitingSupervisor($reservation, $supervisor)); } catch (\Exception $e) {}

                    // Notifier les admins de manière discrète qu'une demande est en attente de signature
                    $admins = User::role('admin')->get();
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\ReservationAwaitingSignatureNotification($reservation));
                    }

                    return redirect()->route('reservations.show', $reservation)
                        ->with('success', 'Demande envoyée à votre responsable pour signature et validation.');
                }
            }
            // Pas de superviseur ou bypass : aller directement vers les admins
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                // Envoyer uniquement la notification (qui gère email + database selon les paramètres)
                $admin->notify(new \App\Notifications\ReservationPendingNotification($reservation));
            }
        }

        // Attacher les participants (utilisateurs additionnels)
        if (!empty($data['participant_ids'])) {
            $participantIds = array_filter($data['participant_ids']);
            // Ne pas inclure le demandeur principal comme participant
            $participantIds = array_diff($participantIds, [$targetUserId]);
            if (!empty($participantIds)) {
                $reservation->participants()->attach($participantIds);
            }
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('success', 'Réservation créée avec succès.');
    }

    /**
     * Bulk update multiple reservations (admin only).
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids'         => 'required|array|min:1',
            'ids.*'       => 'exists:reservations,id',
            'status'      => 'required|in:pending,approved,rejected,cancelled,returned',
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $reservations = Reservation::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($reservations as $reservation) {
            $previousStatus = $reservation->status;
            $updates = ['status' => $request->status];
            if ($request->admin_notes) $updates['admin_notes'] = $request->admin_notes;
            if ($request->status === 'approved' && $reservation->status !== 'approved') {
                $updates['approved_by'] = $request->user()->id;
                $updates['approved_at'] = now();
                try { Mail::to($reservation->user->email)->send(new ReservationConfirmed(
                    $reservation,
                    Setting::get('auto_cancel_enabled', 'false') === 'true',
                    (int) Setting::get('auto_cancel_hours', '24')
                )); } catch (\Exception $e) {}
            }
            if ($request->status === 'rejected') {
                try { Mail::to($reservation->user->email)->send(new ReservationRejected($reservation)); } catch (\Exception $e) {}
            }
            $reservation->update($updates);

            // Mettre à jour le statut des équipements
            if ($request->status === 'approved' && $previousStatus !== 'approved') {
                foreach ($reservation->assets as $asset) {
                    if (in_array($asset->status, ['available', 'en_preparation'])) {
                        $asset->update(['status' => 'reserved']);
                    }
                }
                // Mettre à jour le statut des kits complets dans cette réservation
                $reservation->fresh()->updateKitStatuses('reserved');
            } elseif (in_array($request->status, ['cancelled', 'rejected']) && $previousStatus === 'approved') {
                foreach ($reservation->assets as $asset) {
                    if ($asset->status === 'reserved') {
                        $asset->update(['status' => 'available']);
                    }
                }
                // Remettre les kits à disponible
                $reservation->fresh()->updateKitStatuses('available');
            }
            $count++;
        }

        return back()->with('success', "{$count} réservation(s) mise(s) à jour.");
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'exists:reservations,id',
        ]);

        $reservations = Reservation::whereIn('id', $request->ids)->get();
        $count = 0;

        foreach ($reservations as $reservation) {
            // Libérer les équipements actifs si nécessaire
            if (in_array($reservation->status, ['active', 'approved'])) {
                foreach ($reservation->assets as $asset) {
                    if ($asset->checked_out_to_user_id === $reservation->user_id) {
                        $asset->update([
                            'status'                 => 'available',
                            'checked_out_to_user_id' => null,
                            'expected_return_at'     => null,
                        ]);
                    }
                }
            }
            $reservation->delete();
            $count++;
        }

        return back()->with('success', "{$count} réservation(s) supprimée(s).");
    }

    /**
     * Admin comprehensive update (title, notes, status, dates).
     */
    public function adminUpdate(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'notes'           => 'nullable|string|max:1000',
            'admin_notes'     => 'nullable|string|max:500',
            'status'          => 'required|in:pending,approved,active,returned,cancelled,rejected,awaiting_supervisor',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $updates = $data;

        // Record approval details when transitioning to approved
        if ($data['status'] === 'approved' && $reservation->status !== 'approved') {
            $updates['approved_by'] = $request->user()->id;
            $updates['approved_at'] = now();
            try { Mail::to($reservation->user->email)->send(new ReservationConfirmed(
                    $reservation,
                    Setting::get('auto_cancel_enabled', 'false') === 'true',
                    (int) Setting::get('auto_cancel_hours', '24')
                )); } catch (\Exception $e) {}
        }
        if ($data['status'] === 'rejected' && $reservation->status !== 'rejected') {
            try { Mail::to($reservation->user->email)->send(new ReservationRejected($reservation)); } catch (\Exception $e) {}
        }

        $previousStatus = $reservation->status;
        $reservation->update($updates);

        // Mettre à jour le statut des équipements selon la transition
        if ($data['status'] === 'approved' && $previousStatus !== 'approved') {
            foreach ($reservation->assets as $asset) {
                if (in_array($asset->status, ['available', 'en_preparation'])) {
                    $asset->update(['status' => 'reserved']);
                }
            }
            // Mettre à jour le statut des kits complets dans cette réservation
            $reservation->fresh()->updateKitStatuses('reserved');
        } elseif (in_array($data['status'], ['cancelled', 'rejected']) && $previousStatus === 'approved') {
            foreach ($reservation->assets as $asset) {
                if ($asset->status === 'reserved') {
                    $asset->update(['status' => 'available']);
                }
            }
            // Remettre les kits à disponible
            $reservation->fresh()->updateKitStatuses('available');
        }

        // Mettre à jour les participants (admin uniquement)
        if (isset($data['participant_ids'])) {
            $participantIds = array_filter($data['participant_ids']);
            // Ne pas inclure le demandeur principal comme participant
            $participantIds = array_diff($participantIds, [$reservation->user_id]);
            $reservation->participants()->sync($participantIds);
        }

        return back()->with('success', 'Réservation mise à jour.');
    }

    public function show(Reservation $reservation): Response
    {
        $user       = request()->user();
        $isAdmin    = $user->hasRole('admin');
        $supervisor = $reservation->user->getSupervisor();
        $isSupervisor = $supervisor && $supervisor->id === $user->id;

        if ($reservation->user_id !== $user->id && !$isAdmin && !$isSupervisor) {
            abort(403);
        }

        $reservation->load(['user', 'assets.category', 'approvedBy', 'supervisorApprovedBy', 'formResponse.form', 'participants']);

        // Enrichir les assets avec l'URL d'image
        $assetsWithImage = $reservation->assets->map(function ($asset) {
            $data = $asset->toArray();
            $data['image_url'] = $asset->image
                ? (str_starts_with($asset->image, 'http') ? $asset->image : Storage::url($asset->image))
                : null;
            return $data;
        })->toArray();

        // Récupérer tous les formulaires non-inactifs
        $checkoutForms = CheckoutForm::where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get()
            ->map(function ($form) use ($reservation) {
                // Vérifier si le formulaire a une réponse pour cette réservation
                $response = \App\Models\CheckoutFormResponse::where('checkout_form_id', $form->id)
                    ->where('reservation_id', $reservation->id)
                    ->first();

                return [
                    'id'            => $form->id,
                    'name'          => $form->name,
                    'description'   => $form->description,
                    'status'        => $form->status,
                    'has_response'  => (bool) $response,
                    'submitted_at'  => $response ? $response->created_at->format('d/m/Y H:i') : null,
                ];
            });

        return Inertia::render('Reservations/Show', [
            'reservation' => array_merge($reservation->toArray(), [
                'assets'                       => $assetsWithImage,
                'status_label'                 => $reservation->status_label,
                'status_color'                 => $reservation->status_color,
                'start_date_formatted'         => $reservation->start_date->format('d/m/Y H:i'),
                'end_date_formatted'           => $reservation->end_date->format('d/m/Y H:i'),
                'start_date_input'             => $reservation->start_date->format('Y-m-d\TH:i'),
                'end_date_input'               => $reservation->end_date->format('Y-m-d\TH:i'),
                'approved_at_formatted'        => $reservation->approved_at?->format('d/m/Y H:i'),
                'checkout_at_formatted'        => $reservation->checkout_at?->format('d/m/Y H:i'),
                'return_at_formatted'          => $reservation->return_at?->format('d/m/Y H:i'),
                'supervisor_approved_at_formatted' => $reservation->supervisor_approved_at?->format('d/m/Y H:i'),
            ]),
            'isAdmin'      => $isAdmin,
            'isSupervisor' => $isSupervisor,
            'checkoutForms' => $checkoutForms,
            'allUsers'      => $isAdmin
                ? \App\Models\User::orderBy('name')->get(['id', 'name', 'email'])->toArray()
                : [],
        ]);
    }

    /**
     * Approbation par le superviseur (signature + transmission à l'admin).
     */
    public function supervisorApprove(Request $request, Reservation $reservation)
    {
        $user = $request->user();

        // Vérifie que l'utilisateur courant supervise bien l'étudiant demandeur
        // (gère superviseur direct ET superviseur de groupe, évite le bug multi-groupes)
        if (!$user->supervisedUsers()->where('users.id', $reservation->user_id)->exists()) {
            abort(403, 'Vous n\'êtes pas le responsable de cet utilisateur.');
        }

        if ($reservation->status !== 'awaiting_supervisor') {
            return back()->withErrors(['status' => 'Cette réservation ne peut pas être validée dans son état actuel.']);
        }

        $request->validate([
            'supervisor_signature' => 'required|string|min:10',
        ], [
            'supervisor_signature.required' => 'Votre signature est obligatoire pour valider la demande.',
            'supervisor_signature.min'      => 'La signature est invalide.',
        ]);

        $reservation->update([
            'status'                 => 'pending',
            'supervisor_approved_at' => now(),
            'supervisor_approved_by' => $user->id,
            'supervisor_signature'   => $request->supervisor_signature,
        ]);

        // Marquer les équipements comme "réservé" dès la validation du superviseur
        foreach ($reservation->assets as $asset) {
            if (in_array($asset->status, ['available', 'en_preparation'])) {
                $asset->update(['status' => 'reserved']);
            }
        }

        // Mettre à jour le statut des kits complets dans cette réservation
        $reservation->fresh()->updateKitStatuses('reserved');

        // Notifier les admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            // Envoyer uniquement la notification (qui gère email + database selon les paramètres)
            $admin->notify(new \App\Notifications\ReservationPendingNotification($reservation));
        }

        return back()->with('success', 'Demande approuvée et transmise à l\'administrateur.');
    }

    /**
     * Rejet par le superviseur.
     */
    public function supervisorReject(Request $request, Reservation $reservation)
    {
        $user = $request->user();

        if (!$user->supervisedUsers()->where('users.id', $reservation->user_id)->exists()) {
            abort(403, 'Vous n\'êtes pas le responsable de cet utilisateur.');
        }

        if ($reservation->status !== 'awaiting_supervisor') {
            return back()->withErrors(['status' => 'Cette réservation ne peut pas être refusée dans son état actuel.']);
        }

        $request->validate(['admin_notes' => 'required|string|max:500'], [
            'admin_notes.required' => 'Le motif du refus est obligatoire.',
        ]);

        $reservation->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        try {
            Mail::to($reservation->user->email)->send(new ReservationRejected($reservation));
        } catch (\Exception $e) {}

        return back()->with('success', 'Demande refusée.');
    }

    public function approve(Request $request, Reservation $reservation)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:500']);

        // ── Mode examen : fenêtre de traitement ────────────────────────────────
        if (Setting::get('exam_mode', 'false') === 'true') {
            $processStart = (int) Setting::get('exam_process_start', 7);
            $processEnd   = (int) Setting::get('exam_process_end', 9);
            $currentHour  = (int) now()->format('H');
            if ($currentHour < $processStart || $currentHour >= $processEnd) {
                return back()->withErrors([
                    'approve' => "En mode examen, les réservations ne peuvent être validées qu'entre {$processStart}h et {$processEnd}h.",
                ]);
            }
        }

        $reservation->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'admin_notes' => $request->admin_notes,
        ]);

        // Marquer les équipements comme "réservé" jusqu'au retrait effectif
        foreach ($reservation->assets as $asset) {
            if (in_array($asset->status, ['available', 'en_preparation'])) {
                $asset->update(['status' => 'reserved']);
            }
        }

        // Mettre à jour le statut des kits complets dans cette réservation
        $reservation->fresh()->updateKitStatuses('reserved');

        try {
            Mail::to($reservation->user->email)->send(new ReservationConfirmed(
                    $reservation,
                    Setting::get('auto_cancel_enabled', 'false') === 'true',
                    (int) Setting::get('auto_cancel_hours', '24')
                ));
        } catch (\Exception $e) {}

        return back()->with('success', 'Réservation validée.');
    }

    public function reject(Request $request, Reservation $reservation)
    {
        $request->validate(['admin_notes' => 'required|string|max:500']);

        $reservation->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        // Libérer les équipements s'ils étaient réservés (validation superviseur déjà effectuée)
        foreach ($reservation->assets as $asset) {
            if ($asset->status === 'reserved') {
                $asset->update(['status' => 'available']);
            }
        }

        // Remettre les kits à disponible
        $reservation->fresh()->updateKitStatuses('available');

        try {
            Mail::to($reservation->user->email)->send(new ReservationRejected($reservation));
        } catch (\Exception $e) {}

        return back()->with('success', 'Réservation refusée.');
    }

    public function updateDates(Request $request, Reservation $reservation)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $reservation->update([
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
        ]);

        return back()->with('success', 'Dates mises à jour.');
    }

    /**
     * Confirmation du retrait physique (plus de signature requise ici,
     * la signature superviseur ayant été capturée lors de l'approbation).
     */
    public function checkout(Request $request, Reservation $reservation)
    {
        $user = $request->user();
        if ($reservation->user_id !== $user->id && !$user->hasRole('admin')) {
            abort(403);
        }

        if ($reservation->status !== 'approved') {
            return back()->withErrors(['status' => 'Cette réservation ne peut pas être retirée dans son état actuel.']);
        }

        $reservation->update([
            'status'      => 'active',
            'checkout_at' => now(),
        ]);

        foreach ($reservation->assets as $asset) {
            $asset->update(['status' => 'checked_out']);
            AssetMovement::create([
                'asset_id'       => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id'        => $user->id,
                'action'         => 'checkout',
                'notes'          => "Retrait — Réservation #{$reservation->id}",
            ]);
        }

        if ($request->send_email) {
            try {
                Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation));
            } catch (\Exception $e) {}
        }

        return back()->with('success', 'Retrait confirmé. Matériel sorti.');
    }

    public function return(Request $request, Reservation $reservation)
    {
        $reservation->update([
            'status'    => 'returned',
            'return_at' => now(),
        ]);

        foreach ($reservation->assets as $asset) {
            $asset->update(['status' => 'available']);
            $reservation->assets()->updateExistingPivot($asset->id, ['returned_at' => now()]);
            AssetMovement::create([
                'asset_id'       => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id'        => $request->user()->id,
                'action'         => 'return',
                'notes'          => "Retour — Réservation #{$reservation->id}",
            ]);
            WaitlistController::notifyWaitlist($asset);
        }

        // Notifier l'utilisateur du retour confirmé
        // Notifications et email : uniquement pour les réservations parentes ou indépendantes
        $shouldNotify = !$reservation->parent_reservation_id;

        // Si c'est une réservation parente avec des enfants, vérifier que tous les enfants sont aussi retournés
        if ($shouldNotify && $reservation->childReservations()->exists()) {
            $allChildrenReturned = $reservation->childReservations()
                ->where('status', '!=', 'returned')
                ->doesntExist();
            $shouldNotify = $allChildrenReturned;
        }

        if ($shouldNotify) {
            $reservation->user->notify(new \App\Notifications\ReservationReturnedNotification($reservation));
            try {
                Mail::to($reservation->user->email)->send(new ReservationReturned($reservation));
            } catch (\Exception $e) {}
        }

        // Si c'est une réservation enfant qui vient d'être retournée, vérifier si le parent peut maintenant envoyer le mail
        if (!$shouldNotify && $reservation->parent_reservation_id) {
            $parent = $reservation->parentReservation;
            if ($parent && $parent->status === 'returned') {
                // Vérifier si tous les enfants du parent sont maintenant retournés
                $allChildrenReturned = $parent->childReservations()
                    ->where('status', '!=', 'returned')
                    ->doesntExist();

                if ($allChildrenReturned) {
                    // Envoyer le mail pour le parent maintenant
                    $parent->user->notify(new \App\Notifications\ReservationReturnedNotification($parent));
                    try {
                        Mail::to($parent->user->email)->send(new ReservationReturned($parent));
                    } catch (\Exception $e) {}
                }
            }
        }

        return back()->with('success', 'Retour confirmé. Matériel disponible. L\'utilisateur a été notifié.');
    }

    /**
     * Retour manuel d'UN équipement unique (sans scan — pour les assets sans étiquette physique).
     * Admin only (route protégée).
     */
    public function returnAsset(Request $request, Reservation $reservation, Asset $asset)
    {
        if ($reservation->status !== 'active') {
            return back()->withErrors(['status' => 'La réservation n\'est pas active.']);
        }

        $data = $request->validate([
            'condition'    => 'nullable|in:good,damaged,lost',
            'return_notes' => 'nullable|string|max:1000',
            'scratch_lost' => 'nullable|boolean',
            'belge_lost'   => 'nullable|boolean',
        ]);

        $condition    = $data['condition'] ?? 'good';
        $returnNotes  = $data['return_notes'] ?? null;
        $goodCondition = $condition === 'good';
        $isMissing     = $condition === 'lost';
        $scratchLost   = $request->boolean('scratch_lost');
        $belgeLost     = $request->boolean('belge_lost');

        // Incorporer les notes d'accessoires manquants
        $accessoryNotes = [];
        if ($scratchLost) $accessoryNotes[] = 'Velcro/scratch manquant (déclaré perdu)';
        if ($belgeLost)   $accessoryNotes[] = 'Belge manquant (déclaré perdu)';
        if (!empty($accessoryNotes)) {
            $lostStr     = implode(', ', $accessoryNotes);
            $returnNotes = $returnNotes ? "{$lostStr} — {$returnNotes}" : $lostStr;
        }

        // L'asset doit appartenir à cette réservation
        $pivot = $reservation->assets()->find($asset->id)?->pivot;
        if (!$pivot) {
            abort(404, 'Équipement non trouvé dans cette réservation.');
        }
        if ($pivot->returned_at) {
            return back()->with('info', "« {$asset->name} » a déjà été retourné.");
        }

        // Marquer cet asset comme retourné
        $reservation->assets()->updateExistingPivot($asset->id, [
            'returned_at'    => now(),
            'good_condition' => $goodCondition,
            'is_missing'     => $isMissing,
            'return_notes'   => $returnNotes ?? ($isMissing ? 'Équipement déclaré perdu' : 'Retour manuel'),
            'returned_by_user_id' => $request->user()->id,
        ]);

        // Si perdu → statut maintenance, sinon available
        $newStatus = $isMissing ? 'maintenance' : 'available';
        $assetUpdateData = [
            'status'                 => $newStatus,
            'checked_out_to_user_id' => null,
            'expected_return_at'     => null,
        ];
        if ($scratchLost) $assetUpdateData['has_scratch'] = false;
        if ($belgeLost)   $assetUpdateData['has_belge']   = false;
        $asset->update($assetUpdateData);

        $conditionLabel = match($condition) {
            'good'    => 'Bon état',
            'damaged' => 'Endommagé',
            'lost'    => 'Perdu',
            default   => 'Bon état',
        };

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => $request->user()->id,
            'action'         => 'return',
            'notes'          => "Retour — Réservation #{$reservation->id} — État : {$conditionLabel}" . ($returnNotes ? " — {$returnNotes}" : ''),
        ]);

        // Si tous les assets sont retournés → fermer la réservation + notifier
        $reservation->refresh();
        if ($reservation->assets->every(fn($a) => $a->pivot->returned_at !== null)) {
            $reservation->update(['status' => 'returned', 'return_at' => now()]);

            // Notifications et email : uniquement pour les réservations parentes ou indépendantes
            $shouldNotify = !$reservation->parent_reservation_id;

            // Si c'est une réservation parente avec des enfants, vérifier que tous les enfants sont aussi retournés
            if ($shouldNotify && $reservation->childReservations()->exists()) {
                $allChildrenReturned = $reservation->childReservations()
                    ->where('status', '!=', 'returned')
                    ->doesntExist();
                $shouldNotify = $allChildrenReturned;
            }

            if ($shouldNotify) {
                $reservation->user->notify(new \App\Notifications\ReservationReturnedNotification($reservation));
                try {
                    Mail::to($reservation->user->email)->send(new ReservationReturned($reservation));
                } catch (\Exception $e) {}
            }

            // Si c'est une réservation enfant qui vient d'être retournée, vérifier si le parent peut maintenant envoyer le mail
            if (!$shouldNotify && $reservation->parent_reservation_id) {
                $parent = $reservation->parentReservation;
                if ($parent && $parent->status === 'returned') {
                    // Vérifier si tous les enfants du parent sont maintenant retournés
                    $allChildrenReturned = $parent->childReservations()
                        ->where('status', '!=', 'returned')
                        ->doesntExist();

                    if ($allChildrenReturned) {
                        // Envoyer le mail pour le parent maintenant
                        $parent->user->notify(new \App\Notifications\ReservationReturnedNotification($parent));
                        try {
                            Mail::to($parent->user->email)->send(new ReservationReturned($parent));
                        } catch (\Exception $e) {}
                    }
                }
            }
        }

        // Si endommagé → créer automatiquement une fiche maintenance
        if ($condition === 'damaged') {
            Maintenance::create([
                'asset_id'   => $asset->id,
                'created_by' => $request->user()->id,
                'title'      => "Retour endommagé — {$asset->name}",
                'description'=> $returnNotes ?: "Équipement retourné endommagé lors de la réservation #{$reservation->id}.",
                'started_at' => now()->toDateString(),
                'status'     => 'in_progress',
            ]);
            // L'asset reste disponible (pas en maintenance), sauf si l'admin veut le bloquer
        }

        // Notifier la liste d'attente si l'asset redevient disponible
        if (!$isMissing) {
            WaitlistController::notifyWaitlist($asset);
        }

        $msg = "Retour de « {$asset->name} » enregistré — {$conditionLabel}.";
        if ($isMissing) {
            $msg .= ' Équipement passé en maintenance.';
        }
        return back()->with('success', $msg);
    }

    public function cancel(Request $request, Reservation $reservation)
    {
        if ($reservation->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            abort(403);
        }

        $isAdmin = $request->user()->hasRole('admin');
        $reason  = null;

        if ($isAdmin) {
            $request->validate(['reason' => 'nullable|string|max:1000']);
            $reason = $request->input('reason');
        }

        $previousStatus = $reservation->status;
        $updateData     = ['status' => 'cancelled'];
        if ($reason) {
            $updateData['admin_notes'] = $reason;
        }
        $reservation->update($updateData);

        // Libérer les équipements si la réservation était approuvée ou active
        if (in_array($previousStatus, ['approved', 'active', 'en_preparation'])) {
            foreach ($reservation->assets as $asset) {
                if (in_array($asset->status, ['reserved', 'checked_out', 'en_preparation'])) {
                    $asset->update([
                        'status'                 => 'available',
                        'checked_out_to_user_id' => null,
                        'expected_return_at'     => null,
                    ]);
                }
            }
            // Notifier l'utilisateur si c'est un admin qui annule
            if ($isAdmin) {
                try {
                    Mail::to($reservation->user->email)->send(new ReservationCancelledByAdmin($reservation));
                } catch (\Exception $e) {}
            }
        }

        return back()->with('success', 'Réservation annulée.' . ($reason ? ' Motif : ' . $reason : ''));
    }

    public function sendEmail(Reservation $reservation)
    {
        try {
            Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation));
            return back()->with('success', 'Email envoyé.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }

    public function calendarEvents(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');

        $query = Reservation::with(['user', 'assets.category']);

        // Visibilité complète : admin ou rôle autorisé via paramètres
        $canSeeAll = $isAdmin;
        if (!$canSeeAll) {
            foreach (['professeur', 'etudiant', 'personnel'] as $role) {
                if ($user->hasRole($role)) {
                    $canSeeAll = \App\Models\Setting::get("calendar_visibility_{$role}", 'false') === 'true';
                    break;
                }
            }
        }

        if ($canSeeAll) {
            $query->whereNotIn('status', ['cancelled', 'rejected']);
        } else {
            $query->where('user_id', $user->id)
                  ->whereIn('status', ['awaiting_supervisor', 'pending', 'approved', 'active']);
        }

        $reservations = $query->get()->map(fn($r) => [
            'id'              => $r->id,
            'title'           => $canSeeAll ? "[{$r->user->name}] {$r->title}" : $r->title,
            'start'           => $r->start_date->toISOString(),
            'end'             => $r->end_date->toISOString(),
            'backgroundColor' => $r->status_color,
            'borderColor'     => $r->status_color,
            'extendedProps'   => [
                'status'          => $r->status,
                'status_label'    => $r->status_label,
                'user'            => $r->user->name,
                'assets_count'    => $r->assets->count(),
                'assets'          => $r->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'category'  => $a->category?->name,
                ])->values(),
                'reservation_url' => route('reservations.show', $r->id),
            ],
        ]);

        return response()->json($reservations);
    }

    /**
     * Fiche de sortie imprimable A4.
     */
    public function printView(Request $request, Reservation $reservation): Response
    {
        $reservation->load([
            'user',
            'assets.category',
            'approvedBy',
            'supervisorApprovedBy',
        ]);

        // Récupérer tous les formulaires non-inactifs avec leur statut
        $checkoutForms = CheckoutForm::where('status', '!=', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'status']);

        $selectedFormId = $request->query('form_id');

        // Charger toutes les réponses de formulaires pour cette réservation
        $formResponses = \App\Models\CheckoutFormResponse::where('reservation_id', $reservation->id)
            ->with('form.fields')
            ->get()
            ->keyBy('checkout_form_id')
            ->map(function ($response) {
                return [
                    'id'              => $response->id,
                    'form'            => $response->form ? [
                        'id'     => $response->form->id,
                        'name'   => $response->form->name,
                        'fields' => $response->form->fields,
                    ] : null,
                    'responses'       => $response->responses,
                    'fields_snapshot' => $response->fields_snapshot,
                    'submitted_at'    => $response->submitted_at?->format('d/m/Y H:i'),
                ];
            });

        // Ajouter les URLs d'images aux équipements
        $assetsWithImage = $reservation->assets->map(function ($asset) {
            $data = $asset->toArray();
            $data['image_url'] = $asset->image
                ? (str_starts_with($asset->image, 'http') ? $asset->image : Storage::url($asset->image))
                : null;
            return $data;
        })->toArray();

        // Paramètres d'impression
        $printSettings = Setting::where('group', 'print')
            ->get()
            ->pluck('value', 'key');

        return Inertia::render('Reservations/Print', [
            'reservation'    => array_merge($reservation->toArray(), [
                'status_label'                     => $reservation->status_label,
                'start_date_formatted'             => $reservation->start_date->format('d/m/Y H:i'),
                'end_date_formatted'               => $reservation->end_date->format('d/m/Y H:i'),
                'approved_at_formatted'            => $reservation->approved_at?->format('d/m/Y H:i'),
                'supervisor_approved_at_formatted' => $reservation->supervisor_approved_at?->format('d/m/Y H:i'),
                'assets'                           => $assetsWithImage,
                'form_responses'                   => $formResponses,
            ]),
            'checkoutForms'  => $checkoutForms,
            'selectedFormId' => $selectedFormId ? (int) $selectedFormId : null,
            'schoolName'     => Setting::get('school_name', 'Établissement'),
            'schoolAddress'  => Setting::get('school_address', ''),
            'printDate'      => now()->format('d/m/Y H:i'),
            'printSettings'  => $printSettings,
        ]);
    }

    /**
     * Admin ou proprietaire : ajouter un equipement a une reservation existante.
     * - Admin : tous statuts sauf returned/cancelled/rejected
     * - Proprietaire : uniquement pending ou awaiting_supervisor (sans re-validation superviseur)
     */
    public function addAsset(Request $request, Reservation $reservation)
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isOwner = $reservation->user_id === $user->id;

        if (!$isAdmin && !$isOwner) abort(403);

        if (!$isAdmin) {
            if (!in_array($reservation->status, ['pending', 'awaiting_supervisor'])) {
                return back()->withErrors(['asset_id' => 'Vous ne pouvez modifier les equipements que sur une reservation en attente.']);
            }
        }

        $data = $request->validate([
            'asset_id' => 'required|exists:assets,id',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);

        if ($reservation->assets()->find($asset->id)) {
            return back()->withErrors(['asset_id' => "« {$asset->name} » est deja dans cette reservation."]);
        }

        // Verifier disponibilite (sauf si actif — peut ajouter en cours d'attribution)
        if (!in_array($reservation->status, ['active'])) {
            $avail = $asset->getAvailabilityForPeriod($reservation->start_date, $reservation->end_date);
            if ($avail['status'] === 'unavailable') {
                return back()->withErrors(['asset_id' => "Cet equipement n'est pas disponible pour cette periode : " . $avail['reason']]);
            }
        }

        $reservation->assets()->attach($asset->id);

        // Requête AJAX directe (ex: page Attribution) → réponse JSON
        if (!$request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'asset'   => [
                    'id'        => $asset->id,
                    'name'      => $asset->name,
                    'asset_tag' => $asset->asset_tag,
                    'qr_code'   => (bool) $asset->asset_tag,
                ],
            ]);
        }

        return back()->with('success', "« {$asset->name} » ajoute a la reservation.");
    }

    /**
     * Admin ou proprietaire : retirer un equipement d'une reservation existante.
     * - Admin : tous statuts sauf returned/cancelled/rejected
     * - Proprietaire : uniquement pending ou awaiting_supervisor (sans re-validation superviseur)
     */
    public function removeAsset(Request $request, Reservation $reservation, Asset $asset)
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');
        $isOwner = $reservation->user_id === $user->id;

        if (!$isAdmin && !$isOwner) abort(403);

        if (!$isAdmin) {
            if (!in_array($reservation->status, ['pending', 'awaiting_supervisor'])) {
                return back()->withErrors(['asset_id' => 'Vous ne pouvez modifier les equipements que sur une reservation en attente.']);
            }
        }

        $pivot = $reservation->assets()->find($asset->id)?->pivot;

        if (!$pivot) {
            return back()->withErrors(['asset_id' => 'Equipement non trouve dans cette reservation.']);
        }

        if ($pivot->checkout_at && !$pivot->returned_at) {
            return back()->withErrors(['asset_id' => "« {$asset->name} » est deja sorti, impossible de le retirer."]);
        }

        $reservation->assets()->detach($asset->id);

        // Requête AJAX directe (ex: page Attribution) → réponse JSON
        if (!$request->header('X-Inertia')) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', "« {$asset->name} » retire de la reservation.");
    }

    public function destroy(Reservation $reservation)
    {
        // Route already protected by role:admin middleware
        $id = $reservation->id;

        // Libérer tous les équipements de la réservation (sauf ceux déjà retournés si la réservation était active)
        if (in_array($reservation->status, ['approved', 'active', 'en_preparation', 'pending', 'supervisor_approved'])) {
            foreach ($reservation->assets as $asset) {
                // Si la réservation était active, vérifier si l'équipement a été retourné
                if ($reservation->status === 'active' && $asset->pivot->returned_at) {
                    continue; // Ne pas toucher aux équipements déjà retournés
                }

                // Libérer l'équipement s'il est dans un statut lié à cette réservation
                if (in_array($asset->status, ['reserved', 'checked_out', 'en_preparation'])) {
                    $asset->update([
                        'status'                 => 'available',
                        'checked_out_to_user_id' => null,
                        'expected_return_at'     => null,
                    ]);
                }
            }
        }

        $reservation->delete();

        return redirect()->route('reservations.index')
            ->with('success', "Réservation #{$id} supprimée définitivement.");
    }

    /**
     * Associer une ou plusieurs réservations à une réservation parente.
     * Seul l'admin peut faire cette action.
     */
    public function associateReservations(Request $request)
    {
        $request->validate([
            'parent_id'      => 'required|exists:reservations,id',
            'child_ids'      => 'required|array|min:1',
            'child_ids.*'    => 'required|exists:reservations,id',
        ]);

        $parentId = $request->input('parent_id');
        $childIds = $request->input('child_ids');

        // Vérifier que la réservation parente n'est pas elle-même un enfant
        $parent = Reservation::find($parentId);
        if ($parent->parent_reservation_id) {
            return back()->withErrors(['parent_id' => 'Cette réservation est déjà associée à une autre réservation.']);
        }

        // Vérifier que les enfants ne sont pas déjà associés
        $alreadyAssociated = Reservation::whereIn('id', $childIds)
            ->whereNotNull('parent_reservation_id')
            ->pluck('id');

        if ($alreadyAssociated->isNotEmpty()) {
            return back()->withErrors(['child_ids' => 'Certaines réservations sont déjà associées à une autre réservation.']);
        }

        // Associer les réservations
        Reservation::whereIn('id', $childIds)->update(['parent_reservation_id' => $parentId]);

        $count = count($childIds);
        return back()->with('success', "{$count} réservation(s) associée(s) avec succès.");
    }

    /**
     * Dissocier une réservation de sa parente.
     * Seul l'admin peut faire cette action.
     */
    public function dissociateReservation(Request $request, Reservation $reservation)
    {
        if (!$reservation->parent_reservation_id) {
            return back()->withErrors(['error' => 'Cette réservation n\'est pas associée à une autre.']);
        }

        $reservation->update(['parent_reservation_id' => null]);

        return back()->with('success', 'Réservation dissociée avec succès.');
    }
}
