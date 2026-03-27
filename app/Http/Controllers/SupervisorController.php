<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupervisorController extends Controller
{
    /**
     * Page "Mon groupe" — visible uniquement pour les superviseurs.
     */
    public function group(Request $request): Response
    {
        $user = $request->user();

        // Accessible aux superviseurs de groupes, aux utilisateurs avec supervisor_id direct,
        // et aux professeurs (même sans supervisés assignés encore)
        $canAccess = $user->isSupervisor()
            || $user->hasRole('professeur')
            || $user->hasPermissionTo('access_supervisor_panel');

        if (!$canAccess) {
            abort(403, 'Accès réservé aux superviseurs et professeurs.');
        }

        // ── Groupes dont l'utilisateur est superviseur (via UserGroup)
        $supervisedGroups = $user->supervisedGroups()
            ->with(['members' => function ($q) use ($user) {
                $q->wherePivot('role', 'member')->where('users.id', '!=', $user->id);
            }])
            ->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'name'        => $g->name,
                'description' => $g->description,
                'members'     => $g->members->map(fn($u) => [
                    'id'         => $u->id,
                    'name'       => $u->name,
                    'email'      => $u->email,
                    'department' => $u->department,
                ]),
            ]);

        // ── Subordonné(s) direct(s) via supervisor_id
        $directSubordinates = $user->subordinates()
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'department' => $u->department,
            ]);

        // ── IDs de tous les utilisateurs supervisés (groupes + directs)
        $supervisedUserIds = $user->supervisedUsers()->pluck('id');

        // Demandes en attente de validation (awaiting_supervisor)
        $pendingApprovals = Reservation::with(['user', 'assets.category'])
            ->whereIn('user_id', $supervisedUserIds)
            ->where('status', 'awaiting_supervisor')
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'title'        => $r->title,
                'user'         => ['name' => $r->user->name, 'email' => $r->user->email],
                'start_date'   => $r->start_date->format('d/m/Y H:i'),
                'end_date'     => $r->end_date->format('d/m/Y H:i'),
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'status_color' => $r->status_color,
                'assets_count' => $r->assets->count(),
                'assets'       => $r->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'category'  => $a->category?->name,
                ]),
                'notes'        => $r->notes,
                'url'          => route('reservations.show', $r->id),
            ]);

        // Toutes les réservations récentes des supervisés (30 dernières)
        $recentReservations = Reservation::with(['user', 'assets'])
            ->whereIn('user_id', $supervisedUserIds)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'title'        => $r->title,
                'user'         => ['name' => $r->user->name],
                'start_date'   => $r->start_date->format('d/m/Y H:i'),
                'end_date'     => $r->end_date->format('d/m/Y H:i'),
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'status_color' => $r->status_color,
                'assets_count' => $r->assets->count(),
                'url'          => route('reservations.show', $r->id),
            ]);

        return Inertia::render('Supervisor/Group', [
            'supervisedGroups'    => $supervisedGroups,
            'directSubordinates'  => $directSubordinates,
            'pendingApprovals'    => $pendingApprovals,
            'recentReservations'  => $recentReservations,
        ]);
    }
}
