<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

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
        $pendingApprovals = Reservation::with(['user', 'assets.category', 'kits.assets.category'])
            ->whereIn('user_id', $supervisedUserIds)
            ->where('status', 'awaiting_supervisor')
            ->oldest() // les plus anciennes en premier (plus urgentes)
            ->get()
            ->map(function ($r) {
                $minutesWaiting  = (int) $r->created_at->diffInMinutes(now());
                $hoursUntilStart = round($r->start_date->diffInMinutes(now(), false) / 60, 1);
                // Niveaux d'urgence
                $urgency = 'low';
                if ($r->start_date->isPast() || $hoursUntilStart >= 0 && $hoursUntilStart < 2) {
                    $urgency = 'critical'; // début passé ou dans < 2h
                } elseif ($minutesWaiting >= 1440 || ($hoursUntilStart >= 0 && $hoursUntilStart < 24)) {
                    $urgency = 'high';    // attente > 24h ou début dans < 24h
                } elseif ($minutesWaiting >= 240) {
                    $urgency = 'medium';  // attente > 4h
                }
                return [
                    'id'              => $r->id,
                    'title'           => $r->title,
                    'user'            => ['name' => $r->user->name, 'email' => $r->user->email],
                    'start_date'      => $r->start_date->format('d/m/Y H:i'),
                    'end_date'        => $r->end_date->format('d/m/Y H:i'),
                    'start_date_ts'   => $r->start_date->timestamp,
                    'created_at_ts'   => $r->created_at->timestamp,
                    'status'          => $r->status,
                    'status_label'    => $r->status_label,
                    'status_color'    => $r->status_color,
                    'assets_count'    => $r->assets->count(),
                    'kits'            => $r->kits->map(fn($k) => [
                        'id'     => $k->id,
                        'name'   => $k->name,
                        'assets' => $k->assets->map(fn($a) => [
                            'id'        => $a->id,
                            'name'      => $a->name,
                            'asset_tag' => $a->asset_tag,
                            'category'  => $a->category?->name,
                        ]),
                    ]),
                    'standalone_assets' => (function() use ($r) {
                        // Assets individuels : ceux qui n'appartiennent à aucun kit de cette réservation
                        $kitAssetIds = $r->kits->flatMap(fn($k) => $k->assets->pluck('id'))->unique();
                        // Également exclure l'asset du kit_id legacy
                        if ($r->kit_id) {
                            $legacyKit = $r->kits->firstWhere('id', $r->kit_id);
                            if ($legacyKit) $kitAssetIds = $kitAssetIds->merge($legacyKit->assets->pluck('id'));
                        }
                        return $r->assets
                            ->filter(fn($a) => !$kitAssetIds->contains($a->id))
                            ->map(fn($a) => [
                                'id'        => $a->id,
                                'name'      => $a->name,
                                'asset_tag' => $a->asset_tag,
                                'category'  => $a->category?->name,
                            ])->values();
                    })(),
                    'notes'           => $r->notes,
                    'url'             => route('reservations.show', $r->id),
                    'urgency'         => $urgency,
                    'minutes_waiting' => $minutesWaiting,
                    'start_is_past'   => $r->start_date->isPast(),
                ];
            });

        // Toutes les réservations récentes des supervisés (30 dernières)
        // Exclure awaiting_supervisor car déjà listées dans pendingApprovals (évite les doublons)
        $recentReservations = Reservation::with(['user', 'assets'])
            ->whereIn('user_id', $supervisedUserIds)
            ->whereNotIn('status', ['cancelled', 'rejected', 'awaiting_supervisor'])
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

    /**
     * Endpoint léger pour le polling côté client.
     * Retourne les IDs + count des réservations en attente de signature.
     */
    public function pollPending(Request $request): JsonResponse
    {
        $user = $request->user();

        $canAccess = $user->isSupervisor()
            || $user->hasRole('professeur')
            || $user->hasPermissionTo('access_supervisor_panel');

        if (!$canAccess) {
            return response()->json(['count' => 0, 'ids' => []]);
        }

        $supervisedUserIds = $user->supervisedUsers()->pluck('id');

        $pending = Reservation::whereIn('user_id', $supervisedUserIds)
            ->where('status', 'awaiting_supervisor')
            ->oldest()
            ->get(['id', 'created_at', 'start_date'])
            ->map(fn($r) => [
                'id'            => $r->id,
                'created_at_ts' => $r->created_at->timestamp,
                'start_date_ts' => $r->start_date->timestamp,
            ]);

        return response()->json([
            'count' => $pending->count(),
            'ids'   => $pending->pluck('id'),
            'items' => $pending,
        ]);
    }
}
