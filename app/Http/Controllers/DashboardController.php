<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Reservation;
use App\Models\Category;
use App\Models\Setting;
use App\Models\TodoTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        if ($isAdmin) {
            // ── Rappels de retour imminents ────────────────────────────────────────
            $reminderEnabled = Setting::get('return_reminder_enabled', 'true') === 'true';
            $reminderMinutes = (int) Setting::get('return_reminder_minutes', '30');

            $upcomingReturns = collect();
            if ($reminderEnabled && $reminderMinutes > 0) {
                $upcomingReturns = Reservation::with(['user', 'assets'])
                    ->where('status', 'active')
                    ->where('end_date', '>', now())
                    ->where('end_date', '<=', now()->addMinutes($reminderMinutes))
                    ->orderBy('end_date')
                    ->get()
                    ->map(fn($r) => [
                        'id'           => $r->id,
                        'title'        => $r->title,
                        'user'         => $r->user->name,
                        'end_date'     => $r->end_date->format('d/m/Y H:i'),
                        'end_date_ts'  => $r->end_date->timestamp,
                        'assets_count' => $r->assets->count(),
                        'assets'       => $r->assets->map(fn($a) => $a->name)->join(', '),
                        'minutes_left' => (int) now()->diffInMinutes($r->end_date, false),
                    ]);
            }

            // Toutes les réservations actives (matériel sorti) — pour les compteurs du dashboard
            $checkoutReservations = Reservation::with(['user', 'assets'])
                ->where('status', 'active')
                ->orderBy('end_date')
                ->get()
                ->map(fn($r) => [
                    'id'          => $r->id,
                    'title'       => $r->title,
                    'user'        => $r->user->name,
                    'end_date'    => $r->end_date->format('d/m/Y H:i'),
                    'end_date_ts' => $r->end_date->timestamp,
                    'assets'      => $r->assets->map(fn($a) => $a->name)->join(', '),
                    'assets_count'=> $r->assets->count(),
                    'is_overdue'  => $r->end_date->lt(now()),
                ]);

            // Réservations en retard (actives avec end_date dépassée)
            $overdueReturns = Reservation::with(['user', 'assets'])
                ->where('status', 'active')
                ->where('end_date', '<', now())
                ->orderBy('end_date')
                ->get()
                ->map(fn($r) => [
                    'id'              => $r->id,
                    'title'           => $r->title,
                    'user'            => $r->user->name,
                    'end_date'        => $r->end_date->format('d/m/Y H:i'),
                    'end_date_ts'     => $r->end_date->timestamp,
                    'assets_count'    => $r->assets->count(),
                    'assets'          => $r->assets->map(fn($a) => $a->name)->join(', '),
                    'minutes_overdue' => (int) $r->end_date->diffInMinutes(now()),
                ]);

            // ── Réservations approuvées en attente de retrait ─────────────────────
            $autoCancelEnabled = Setting::get('auto_cancel_enabled', 'false') === 'true';
            $autoCancelHours   = (int) Setting::get('auto_cancel_hours', '24');

            $approvedReservations = Reservation::with(['user', 'assets'])
                ->approved()
                ->latest('approved_at')
                ->get()
                ->map(function ($r) use ($autoCancelEnabled, $autoCancelHours) {
                    $hoursElapsed = $r->approved_at ? (int) $r->approved_at->diffInHours(now()) : null;
                    $hoursLeft    = ($autoCancelEnabled && $r->approved_at)
                        ? max(0, $autoCancelHours - $hoursElapsed)
                        : null;
                    return [
                        'id'           => $r->id,
                        'title'        => $r->title,
                        'user'         => $r->user->name,
                        'start_date'   => $r->start_date->format('d/m/Y H:i'),
                        'approved_at'  => $r->approved_at?->format('d/m/Y H:i'),
                        'assets_count' => $r->assets->count(),
                        'assets'       => $r->assets->map(fn($a) => $a->name)->join(', '),
                        'hours_elapsed'=> $hoursElapsed,
                        'hours_left'   => $hoursLeft,
                        'at_risk'      => $autoCancelEnabled && $hoursLeft !== null && $hoursLeft <= 4,
                    ];
                });

            // Optimisation : regrouper les requêtes COUNT en une seule requête par table
            $assetCounts = Asset::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $reservationCounts = Reservation::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $stats = [
                'total_assets'              => $assetCounts->sum(),
                'available_assets'          => $assetCounts->get('available', 0),
                'checked_out_assets'        => $assetCounts->get('checked_out', 0),
                'maintenance_assets'        => $assetCounts->get('maintenance', 0),
                'pending_reservations'      => $reservationCounts->get('pending', 0),
                'approved_reservations'     => $reservationCounts->get('approved', 0),
                'active_reservations'       => $reservationCounts->get('active', 0),
                'awaiting_supervisor'       => $reservationCounts->get('awaiting_supervisor', 0),
                'total_users'               => User::count(),
                'total_categories'          => Category::count(),
                'upcoming_returns'          => $upcomingReturns->count(),
                'overdue_returns'           => $overdueReturns->count(),
            ];

            $pendingReservations = Reservation::with(['user', 'assets'])
                ->pending()
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'user' => $r->user->name,
                    'start_date' => $r->start_date->format('d/m/Y H:i'),
                    'end_date' => $r->end_date->format('d/m/Y H:i'),
                    'assets_count' => $r->assets->count(),
                    'status' => $r->status,
                    'status_label' => $r->status_label,
                ]);

            $recentMovements = \App\Models\AssetMovement::with(['asset', 'user'])
                ->latest()
                ->take(10)
                ->get()
                ->map(fn($m) => [
                    'id' => $m->id,
                    'asset' => $m->asset->name ?? 'N/A',
                    'asset_tag' => $m->asset->asset_tag ?? 'N/A',
                    'user' => $m->user->name ?? 'Système',
                    'action' => $m->action_label,
                    'created_at' => $m->created_at->format('d/m/Y H:i'),
                ]);

            // ── Récapitulatif To-Do ────────────────────────────────────────────────
            // Tâches non terminées avec due_date, sur les 30 prochains jours + déjà en retard
            $todoTasks = TodoTask::with(['assignedTo:id,name', 'project:id,name,color', 'labels:id,name,color'])
                ->whereNull('parent_task_id')
                ->where('status', '!=', 'done')
                ->whereNotNull('due_date')
                ->where('due_date', '<=', now()->addDays(30))
                ->orderBy('due_date')
                ->get()
                ->map(fn($t) => [
                    'id'          => $t->id,
                    'title'       => $t->title,
                    'due_date'    => $t->due_date->format('Y-m-d'),
                    'due_label'   => $t->due_date->format('d/m/Y'),
                    'is_overdue'  => $t->due_date->lt(now()->startOfDay()),
                    'is_today'    => $t->due_date->isToday(),
                    'is_tomorrow' => $t->due_date->isTomorrow(),
                    'priority'    => $t->priority,
                    'status'      => $t->status,
                    'project'     => ['id' => $t->project->id, 'name' => $t->project->name, 'color' => $t->project->color],
                    'assigned_to' => $t->assignedTo ? $t->assignedTo->name : null,
                    'labels'      => $t->labels->map(fn($l) => ['name' => $l->name, 'color' => $l->color])->values(),
                ]);

            // ── Données graphiques ─────────────────────────────────────────────────
            // Réservations par mois (6 derniers mois)
            $months = collect(range(5, 0))->map(fn($i) => now()->subMonths($i));
            $reservationsByMonth = $months->map(fn($month) => [
                'label' => ucfirst($month->translatedFormat('M y')),
                'count' => Reservation::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
            ])->values();

            // Répartition équipements par statut
            $statuses = AssetStatus::allCached();
            $assetsByStatus = $statuses->map(fn($s) => [
                'label' => $s->label,
                'count' => Asset::where('status', $s->key)->count(),
                'color' => $s->color,
            ])->filter(fn($s) => $s['count'] > 0)->values();

            // Top 5 équipements les plus réservés
            $topAssets = Asset::withCount('reservations')
                ->orderByDesc('reservations_count')
                ->take(5)
                ->get(['id', 'name', 'asset_tag'])
                ->map(fn($a) => [
                    'name'  => $a->name,
                    'tag'   => $a->asset_tag,
                    'count' => $a->reservations_count,
                ]);

            return Inertia::render('Dashboard', [
                'stats'                => $stats,
                'pendingReservations'   => $pendingReservations,
                'approvedReservations'  => $approvedReservations,
                'recentMovements'       => $recentMovements,
                'upcomingReturns'       => $upcomingReturns,
                'overdueReturns'        => $overdueReturns,
                'checkoutReservations'  => $checkoutReservations,
                'reminderMinutes'      => $reminderMinutes,
                'reminderEnabled'      => $reminderEnabled,
                'autoCancelEnabled'    => $autoCancelEnabled,
                'autoCancelHours'      => $autoCancelHours,
                'reservationsByMonth'  => $reservationsByMonth,
                'assetsByStatus'       => $assetsByStatus,
                'topAssets'            => $topAssets,
                'todoTasks'            => $todoTasks,
            ]);
        }

        // User dashboard
        $myReservations = Reservation::with('assets')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved', 'active'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'title'        => $r->title,
                'start_date'   => $r->start_date->format('d/m/Y H:i'),
                'end_date'     => $r->end_date->format('d/m/Y H:i'),
                'end_date_ts'  => $r->end_date->timestamp,
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'status_color' => $r->status_color,
                'assets_count' => $r->assets->count(),
            ]);

        $autoCancelEnabled = Setting::get('auto_cancel_enabled', 'false') === 'true';
        $autoCancelHours   = (int) Setting::get('auto_cancel_hours', '24');

        $stats = [
            'my_pending'  => Reservation::where('user_id', $user->id)->pending()->count(),
            'my_approved' => Reservation::where('user_id', $user->id)->approved()->count(),
            'my_active'   => Reservation::where('user_id', $user->id)->active()->count(),
            'my_total'    => Reservation::where('user_id', $user->id)->count(),
            'available_assets' => Asset::where('status', 'available')->count(),
        ];

        return Inertia::render('Dashboard', array_merge(
            compact('stats', 'myReservations'),
            [
                'autoCancelEnabled' => $autoCancelEnabled,
                'autoCancelHours'   => $autoCancelHours,
            ]
        ));
    }
}
