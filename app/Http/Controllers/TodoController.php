<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Setting;
use App\Models\TodoLabel;
use App\Models\TodoProject;
use App\Models\TodoTask;
use App\Models\TodoTaskReminder;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TodoController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // ACCÈS
    // ─────────────────────────────────────────────────────────────────────

    private function checkAccess(): void
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && Setting::get('todo_enabled', 'false') !== 'true') {
            abort(403, 'La liste de tâches n\'est pas activée.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PAGE PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->checkAccess();

        $user    = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $baseQuery = fn() => TodoProject::where(function ($q) use ($user, $isAdmin) {
            $q->where('created_by', $user->id);
            if ($isAdmin) {
                $q->orWhere('is_shared', true);
            }
        });

        $mapProject = fn($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'color'       => $p->color,
            'description' => $p->description,
            'is_shared'   => $p->is_shared,
            'archived_at' => $p->archived_at?->toISOString(),
            'task_count'  => $p->allTasks()->count(),
            'done_count'  => $p->allTasks()->where('status', 'done')->count(),
        ];

        // Projets actifs (non archivés)
        $projects = $baseQuery()
            ->whereNull('archived_at')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map($mapProject);

        // Projets archivés
        $archivedProjects = $baseQuery()
            ->whereNotNull('archived_at')
            ->orderByDesc('archived_at')
            ->get()
            ->map($mapProject);

        // Labels disponibles
        $labels = TodoLabel::orderBy('name')->get(['id', 'name', 'color']);

        // Utilisateurs pour assignation (admin uniquement)
        $users = $isAdmin
            ? User::orderBy('name')->get(['id', 'name', 'email'])
            : collect([]);

        // Réservations récentes/à venir (60 jours passés + 60 jours futurs) pour le picker
        $reservations = Reservation::with('user:id,name')
            ->where('start_date', '>=', now()->subDays(60))
            ->where('start_date', '<=', now()->addDays(60))
            ->whereIn('status', ['approved', 'active', 'pending'])
            ->orderBy('start_date')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'title'      => $r->title,
                'start_date' => $r->start_date->format('d/m/Y'),
                'end_date'   => $r->end_date->format('d/m/Y'),
                'user_name'  => $r->user?->name,
            ]);

        // Équipements pour le picker (admin uniquement) — tous + flag étiquette
        $assets = $isAdmin
            ? \App\Models\Asset::orderBy('name')
                ->get(['id', 'name', 'asset_tag', 'label_to_create', 'label_to_apply'])
                ->map(fn($a) => [
                    'id'              => $a->id,
                    'name'            => $a->name,
                    'asset_tag'       => $a->asset_tag,
                    'label_to_create' => (bool) $a->label_to_create,
                    'label_to_apply'  => (bool) $a->label_to_apply,
                    'has_label_todo'  => $a->label_to_create || $a->label_to_apply,
                ])
            : collect([]);

        return Inertia::render('Todo/Index', [
            'projects'         => $projects,
            'archivedProjects' => $archivedProjects,
            'labels'           => $labels,
            'users'            => $users,
            'reservations'     => $reservations,
            'assets'           => $assets,
            'isAdmin'          => $isAdmin,
            'myUserId'         => $user->id,
            'calendarEnabled'  => GoogleCalendarService::isEnabled() && GoogleCalendarService::isConnected(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROJETS
    // ─────────────────────────────────────────────────────────────────────

    public function storeProject(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:1000',
            'is_shared'   => 'boolean',
        ]);

        $data['created_by'] = Auth::id();
        $data['is_shared']  = Auth::user()->hasRole('admin') && ($data['is_shared'] ?? false);
        $data['position']   = TodoProject::max('position') + 1;

        $project = TodoProject::create($data);

        return response()->json([
            'id'          => $project->id,
            'name'        => $project->name,
            'color'       => $project->color,
            'description' => $project->description,
            'is_shared'   => $project->is_shared,
            'task_count'  => 0,
            'done_count'  => 0,
        ]);
    }

    public function updateProject(Request $request, TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'color'       => 'required|string|max:7',
            'description' => 'nullable|string|max:1000',
            'is_shared'   => 'boolean',
        ]);

        if (!Auth::user()->hasRole('admin')) {
            unset($data['is_shared']);
        }

        $todoProject->update($data);

        return response()->json(['ok' => true]);
    }

    public function destroyProject(TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $todoProject->delete();

        return response()->json(['ok' => true]);
    }

    public function archiveProject(TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $todoProject->update(['archived_at' => now()]);

        return response()->json(['ok' => true, 'archived_at' => $todoProject->archived_at->toISOString()]);
    }

    public function unarchiveProject(TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $todoProject->update(['archived_at' => null]);

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // TÂCHES — LISTE
    // ─────────────────────────────────────────────────────────────────────

    public function tasks(TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $tasks = $todoProject->allTasks()
            ->with(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date', 'assets:id,name,asset_tag,label_to_create,label_to_apply', 'reminders'])
            ->whereNull('parent_task_id')
            ->orderBy('position')
            ->get()
            ->map(fn($t) => $this->formatTask($t));

        return response()->json($tasks);
    }

    // ─────────────────────────────────────────────────────────────────────
    // TÂCHES — CRUD
    // ─────────────────────────────────────────────────────────────────────

    public function storeTask(Request $request, TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $data = $request->validate([
            'title'              => 'required|string|max:500',
            'description'        => 'nullable|string|max:5000',
            'due_date'           => 'nullable|date',
            'start_time'         => 'nullable|date_format:H:i',
            'end_time'           => 'nullable|date_format:H:i',
            'priority'           => 'in:none,low,medium,high,urgent',
            'status'             => 'in:todo,in_progress,done',
            'parent_task_id'     => 'nullable|exists:todo_tasks,id',
            'assigned_to'        => 'nullable|exists:users,id',
            'label_ids'          => 'nullable|array',
            'label_ids.*'        => 'exists:todo_labels,id',
            'reservation_ids'    => 'nullable|array',
            'reservation_ids.*'  => 'exists:reservations,id',
            'asset_ids'          => 'nullable|array',
            'asset_ids.*'        => 'exists:assets,id',
            'reminders'          => 'nullable|array',
            'reminders.*.reminder_at'      => 'required|date',
            'reminders.*.reminder_channel' => 'required|in:notification,email,both',
            'sync_to_calendar'       => 'nullable|boolean',
            'calendar_reminders'     => 'nullable|array',
            'calendar_reminders.*.method'   => 'required|in:popup,email',
            'calendar_reminders.*.minutes'  => 'required|integer|min:0|max:40320',
        ]);

        $syncToCalendar = !empty($data['sync_to_calendar']);
        $data['project_id']  = $todoProject->id;
        $data['created_by']  = Auth::id();
        $data['position']    = $todoProject->allTasks()
            ->where('parent_task_id', $data['parent_task_id'] ?? null)
            ->max('position') + 1;

        $labelIds       = $data['label_ids'] ?? [];
        $reservationIds = $data['reservation_ids'] ?? [];
        $assetIds       = $data['asset_ids'] ?? [];
        $remindersData  = $data['reminders'] ?? [];
        unset($data['label_ids'], $data['reservation_ids'], $data['asset_ids'], $data['reminders'], $data['sync_to_calendar']);

        $task = TodoTask::create($data);
        if ($labelIds)       $task->labels()->sync($labelIds);
        if ($reservationIds) $task->reservations()->sync($reservationIds);
        if ($assetIds)       $task->assets()->sync($assetIds);

        foreach ($remindersData as $r) {
            $task->reminders()->create([
                'reminder_at'      => $r['reminder_at'],
                'reminder_channel' => $r['reminder_channel'],
            ]);
        }

        // Synchronisation Google Calendar si demandée
        $calendarSyncError = null;
        if ($syncToCalendar && $task->due_date
            && GoogleCalendarService::isEnabled()
            && GoogleCalendarService::isConnected()
        ) {
            try {
                $eventId = GoogleCalendarService::syncTask($task);
                $task->update(['sync_to_calendar' => true, 'google_event_id' => $eventId]);
            } catch (\Exception $e) {
                $calendarSyncError = $e->getMessage();
                $task->update(['sync_to_calendar' => true]);
            }
        } elseif ($syncToCalendar) {
            $task->update(['sync_to_calendar' => true]);
        }

        $task->load(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date', 'assets:id,name,asset_tag,label_to_create,label_to_apply', 'reminders']);

        $response = $this->formatTask($task);
        if ($calendarSyncError) {
            $response['calendar_sync_error'] = $calendarSyncError;
        }
        return response()->json($response);
    }

    public function updateTask(Request $request, TodoTask $todoTask)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        $data = $request->validate([
            'title'              => 'sometimes|required|string|max:500',
            'description'        => 'nullable|string|max:5000',
            'due_date'           => 'nullable|date',
            'start_time'         => 'nullable|date_format:H:i',
            'end_time'           => 'nullable|date_format:H:i',
            'priority'           => 'sometimes|in:none,low,medium,high,urgent',
            'status'             => 'sometimes|in:todo,in_progress,done',
            'position'           => 'sometimes|integer',
            'assigned_to'        => 'nullable|exists:users,id',
            'label_ids'          => 'nullable|array',
            'label_ids.*'        => 'exists:todo_labels,id',
            'reservation_ids'    => 'nullable|array',
            'reservation_ids.*'  => 'exists:reservations,id',
            'asset_ids'          => 'nullable|array',
            'asset_ids.*'        => 'exists:assets,id',
            'reminders'          => 'nullable|array',
            'reminders.*.id'               => 'nullable|integer',
            'reminders.*.reminder_at'      => 'required|date',
            'reminders.*.reminder_channel' => 'required|in:notification,email,both',
            'sync_to_calendar'       => 'sometimes|boolean',
            'calendar_reminders'     => 'nullable|array',
            'calendar_reminders.*.method'   => 'required|in:popup,email',
            'calendar_reminders.*.minutes'  => 'required|integer|min:0|max:40320',
        ]);

        if (array_key_exists('label_ids', $data)) {
            $todoTask->labels()->sync($data['label_ids'] ?? []);
            unset($data['label_ids']);
        }
        if (array_key_exists('reservation_ids', $data)) {
            $todoTask->reservations()->sync($data['reservation_ids'] ?? []);
            unset($data['reservation_ids']);
        }
        if (array_key_exists('asset_ids', $data)) {
            $todoTask->assets()->sync($data['asset_ids'] ?? []);
            unset($data['asset_ids']);
        }

        // Réconcilier les rappels
        if (array_key_exists('reminders', $data)) {
            $incomingReminders = collect($data['reminders'] ?? []);
            unset($data['reminders']);

            $keepIds = $incomingReminders->pluck('id')->filter()->values();

            // Supprimer ceux qui ne sont plus dans la liste
            $todoTask->reminders()->whereNotIn('id', $keepIds)->delete();

            foreach ($incomingReminders as $r) {
                if (!empty($r['id'])) {
                    // Mettre à jour l'existant — réinitialiser reminder_sent_at si la date change
                    $existing = $todoTask->reminders()->find($r['id']);
                    if ($existing) {
                        $newAt  = \Carbon\Carbon::parse($r['reminder_at'])->format('Y-m-d H:i');
                        $oldAt  = $existing->reminder_at->format('Y-m-d H:i');
                        $sentAt = ($newAt !== $oldAt) ? null : $existing->reminder_sent_at;
                        $existing->update([
                            'reminder_at'      => $r['reminder_at'],
                            'reminder_channel' => $r['reminder_channel'],
                            'reminder_sent_at' => $sentAt,
                        ]);
                    }
                } else {
                    // Nouveau rappel
                    $todoTask->reminders()->create([
                        'reminder_at'      => $r['reminder_at'],
                        'reminder_channel' => $r['reminder_channel'],
                    ]);
                }
            }
        }

        // ── Détecter si calendar_reminders change (pour patch ciblé) ─────────
        $calendarRemindersChanged = array_key_exists('calendar_reminders', $data);

        // ── Gérer sync_to_calendar (effets de bord Google Calendar) ──────────
        $calendarSyncError = null;

        if (array_key_exists('sync_to_calendar', $data)) {
            $newSync = (bool) $data['sync_to_calendar'];
            unset($data['sync_to_calendar']);

            if ($newSync && !$todoTask->sync_to_calendar) {
                // Activer : sauvegarder d'abord, puis créer l'événement
                $todoTask->update($data);
                $todoTask->refresh();
                $eventId = null;
                try {
                    $eventId = GoogleCalendarService::syncTask($todoTask);
                } catch (\Exception $e) {
                    $calendarSyncError = $e->getMessage();
                }
                $todoTask->update(['sync_to_calendar' => true, 'google_event_id' => $eventId]);

            } elseif (!$newSync && $todoTask->sync_to_calendar) {
                // Désactiver : supprimer l'événement
                if ($todoTask->google_event_id) {
                    try { GoogleCalendarService::deleteEvent($todoTask->google_event_id); } catch (\Exception $e) {}
                }
                $todoTask->update(array_merge($data, ['sync_to_calendar' => false, 'google_event_id' => null]));

            } else {
                // Sync inchangé — sauvegarder les données d'abord
                $todoTask->update($data);
                $todoTask->refresh();

                // Si sync actif et événement existant : mettre à jour Google Calendar
                if ($todoTask->sync_to_calendar && $todoTask->google_event_id) {
                    // Si seules les alertes calendar ont changé : patch ciblé (plus fiable)
                    // Sinon : re-sync complet (titre, date, heure, alertes)
                    if ($calendarRemindersChanged && !isset($data['due_date']) && !isset($data['title'])
                        && !isset($data['start_time']) && !isset($data['end_time'])) {
                        try {
                            $ok = GoogleCalendarService::patchEventReminders(
                                $todoTask->google_event_id,
                                $todoTask->calendar_reminders ?? []
                            );
                            if (!$ok) $calendarSyncError = 'Échec de la mise à jour des alertes Google Calendar.';
                        } catch (\Exception $e) {
                            $calendarSyncError = $e->getMessage();
                        }
                    } else {
                        try {
                            GoogleCalendarService::syncTask($todoTask);
                        } catch (\Exception $e) {
                            $calendarSyncError = $e->getMessage();
                        }
                    }
                }
            }
        } else {
            $todoTask->update($data);
            $todoTask->refresh();
            // Quickstatus ou changement mineur : re-sync si actif
            if ($todoTask->sync_to_calendar && $todoTask->google_event_id) {
                try { GoogleCalendarService::syncTask($todoTask); } catch (\Exception $e) {
                    $calendarSyncError = $e->getMessage();
                }
            }
        }

        $todoTask->load(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date', 'assets:id,name,asset_tag,label_to_create,label_to_apply', 'reminders']);

        $response = $this->formatTask($todoTask);
        if ($calendarSyncError) {
            $response['calendar_sync_error'] = $calendarSyncError;
        }
        return response()->json($response);
    }

    public function destroyTask(TodoTask $todoTask)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        $todoTask->delete();

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RAPPELS — CRUD inline (depuis le modal détail)
    // ─────────────────────────────────────────────────────────────────────

    public function storeReminder(Request $request, TodoTask $todoTask)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        $data = $request->validate([
            'reminder_at'      => 'required|date',
            'reminder_channel' => 'required|in:notification,email,both',
        ]);

        $reminder = $todoTask->reminders()->create($data);

        return response()->json($this->formatReminder($reminder));
    }

    public function updateReminder(Request $request, TodoTask $todoTask, TodoTaskReminder $reminder)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        if ($reminder->todo_task_id !== $todoTask->id) abort(404);

        $data = $request->validate([
            'reminder_at'      => 'required|date',
            'reminder_channel' => 'required|in:notification,email,both',
        ]);

        $newAt = \Carbon\Carbon::parse($data['reminder_at'])->format('Y-m-d H:i');
        $oldAt = $reminder->reminder_at->format('Y-m-d H:i');
        if ($newAt !== $oldAt) {
            $data['reminder_sent_at'] = null;
        }

        $reminder->update($data);

        return response()->json($this->formatReminder($reminder->fresh()));
    }

    public function destroyReminder(TodoTask $todoTask, TodoTaskReminder $reminder)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        if ($reminder->todo_task_id !== $todoTask->id) abort(404);

        $reminder->delete();

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LABELS — CRUD (admin seulement)
    // ─────────────────────────────────────────────────────────────────────

    public function storeLabel(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);
        $data['created_by'] = Auth::id();

        $label = TodoLabel::create($data);

        return response()->json(['id' => $label->id, 'name' => $label->name, 'color' => $label->color]);
    }

    public function destroyLabel(TodoLabel $todoLabel)
    {
        if (!Auth::user()->hasRole('admin')) abort(403);
        $todoLabel->delete();
        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function authorizeProject(TodoProject $project): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return;
        if ($project->created_by !== $user->id && !$project->is_shared) {
            abort(403);
        }
    }

    private function formatReminder(TodoTaskReminder $r): array
    {
        return [
            'id'               => $r->id,
            'reminder_at'      => $r->reminder_at->format('Y-m-d\TH:i'),
            'reminder_channel' => $r->reminder_channel,
            'reminder_sent_at' => $r->reminder_sent_at?->format('Y-m-d H:i'),
        ];
    }

    private function formatTask(TodoTask $t): array
    {
        return [
            'id'             => $t->id,
            'project_id'     => $t->project_id,
            'parent_task_id' => $t->parent_task_id,
            'title'          => $t->title,
            'description'    => $t->description,
            'due_date'       => $t->due_date?->format('Y-m-d'),
            'start_time'     => $t->start_time,
            'end_time'       => $t->end_time,
            'priority'       => $t->priority,
            'status'         => $t->status,
            'position'       => $t->position,
            'assigned_to'    => $t->assignedTo ? ['id' => $t->assignedTo->id, 'name' => $t->assignedTo->name] : null,
            'labels'         => $t->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'subtasks'       => $t->subtasks->map(fn($s) => $this->formatTask($s))->values(),
            'reservations'   => ($t->relationLoaded('reservations') ? $t->reservations : collect())->map(fn($r) => [
                'id'         => $r->id,
                'title'      => $r->title,
                'start_date' => $r->start_date->format('d/m/Y'),
                'end_date'   => $r->end_date->format('d/m/Y'),
            ])->values(),
            'assets'         => ($t->relationLoaded('assets') ? $t->assets : collect())->map(fn($a) => [
                'id'              => $a->id,
                'name'            => $a->name,
                'asset_tag'       => $a->asset_tag,
                'label_to_create' => (bool) $a->label_to_create,
                'label_to_apply'  => (bool) $a->label_to_apply,
            ])->values(),
            'reminders'           => ($t->relationLoaded('reminders') ? $t->reminders : collect())->map(fn($r) => $this->formatReminder($r))->values(),
            'sync_to_calendar'    => (bool) $t->sync_to_calendar,
            'google_event_id'     => $t->google_event_id,
            'calendar_reminders'  => $t->calendar_reminders ?? [],
        ];
    }
}
