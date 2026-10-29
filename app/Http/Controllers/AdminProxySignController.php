<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminProxySignController extends Controller
{
    /**
     * Page admin — liste des réservations en attente de signature superviseur.
     */
    public function index()
    {
        $reservations = Reservation::with(['user.supervisor', 'user.groups.supervisors', 'assets', 'supervisorChosen'])
            ->where('status', 'awaiting_supervisor')
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => $this->mapReservation($r));

        return Inertia::render('Admin/ProxySign', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * L'admin soumet la signature du responsable au nom de celui-ci.
     */
    public function sign(Request $request, Reservation $reservation)
    {
        if ($reservation->status !== 'awaiting_supervisor') {
            return back()->withErrors(['status' => 'Cette réservation n\'est plus en attente de signature.']);
        }

        $data = $request->validate([
            'supervisor_id'        => 'required|exists:users,id',
            'supervisor_signature' => 'required|string|min:10',
        ], [
            'supervisor_id.required'        => 'Le responsable doit être sélectionné.',
            'supervisor_signature.required' => 'La signature est obligatoire.',
            'supervisor_signature.min'      => 'La signature est invalide.',
        ]);

        // Vérifier que le supervisor_id est bien un superviseur de l'étudiant demandeur
        $supervisor = User::findOrFail($data['supervisor_id']);
        $isSupervisorOfUser = $supervisor->supervisedUsers()
            ->where('users.id', $reservation->user_id)
            ->exists();

        if (!$isSupervisorOfUser) {
            return back()->withErrors(['supervisor_id' => 'Cet utilisateur n\'est pas le responsable de l\'étudiant demandeur.']);
        }

        $reservation->update([
            'status'                 => 'pending',
            'supervisor_approved_at' => now(),
            'supervisor_approved_by' => $supervisor->id,
            'supervisor_signature'   => $data['supervisor_signature'],
        ]);

        // Notifier les admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new \App\Mail\ReservationPending($reservation, $admin));
            } catch (\Exception $e) {}
            try {
                $admin->notify(new \App\Notifications\ReservationPendingNotification($reservation));
            } catch (\Exception $e) {}
        }

        return back()->with('success', "Signature de {$supervisor->name} enregistrée. La réservation est transmise à l'administrateur.");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function mapReservation(Reservation $r): array
    {
        return [
            'id'         => $r->id,
            'title'      => $r->title,
            'start_date' => $r->start_date->format('d/m/Y H:i'),
            'end_date'   => $r->end_date->format('d/m/Y H:i'),
            'notes'      => $r->notes,
            'created_at' => $r->created_at->format('d/m/Y H:i'),
            'user'       => [
                'id'    => $r->user->id,
                'name'  => $r->user->name,
                'email' => $r->user->email,
            ],
            'assets_count' => $r->assets->count(),
            'assets'       => $r->assets->map(fn($a) => [
                'id'        => $a->id,
                'name'      => $a->name,
                'asset_tag' => $a->asset_tag,
            ]),
            // Superviseur choisi par l'utilisateur lors de la création de la réservation
            'supervisor_chosen' => $r->supervisorChosen ? [
                'id'   => $r->supervisorChosen->id,
                'name' => $r->supervisorChosen->name,
            ] : null,
            // Superviseur(s) possibles pour cette réservation
            'supervisors' => $this->getSupervisorsForUser($r->user),
        ];
    }

    /**
     * Retourne tous les superviseurs d'un utilisateur
     * (superviseur direct + superviseurs de ses groupes).
     */
    private function getSupervisorsForUser(User $user): array
    {
        $supervisors = collect();

        // Superviseur direct
        if ($user->supervisor_id && $user->supervisor) {
            $supervisors->push($user->supervisor);
        }

        // Superviseurs via groupes
        foreach ($user->groups()->wherePivot('role', 'member')->with('supervisors')->get() as $group) {
            foreach ($group->supervisors as $sup) {
                $supervisors->push($sup);
            }
        }

        return $supervisors->unique('id')->map(fn($s) => [
            'id'   => $s->id,
            'name' => $s->name,
        ])->values()->toArray();
    }
}
