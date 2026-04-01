<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Setting;
use App\Models\TodoLabel;
use App\Models\TodoProject;
use App\Models\TodoTask;
use App\Models\User;
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

        // Projets : les siens + les projets partagés
        $projects = TodoProject::where(function ($q) use ($user, $isAdmin) {
            $q->where('created_by', $user->id);
            if ($isAdmin) {
                $q->orWhere('is_shared', true);
            }
        })
        ->orderBy('position')
        ->orderBy('name')
        ->get()
        ->map(fn($p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'color'       => $p->color,
            'description' => $p->description,
            'is_shared'   => $p->is_shared,
            'task_count'  => $p->allTasks()->count(),
            'done_count'  => $p->allTasks()->where('status', 'done')->count(),
        ]);

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

        return Inertia::render('Todo/Index', [
            'projects'     => $projects,
            'labels'       => $labels,
            'users'        => $users,
            'reservations' => $reservations,
            'isAdmin'      => $isAdmin,
            'myUserId'     => $user->id,
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
        $data['is_shared']  = Auth::user()->is_admin && ($data['is_shared'] ?? false);
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

        if (!Auth::user()->is_admin) {
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

    // ─────────────────────────────────────────────────────────────────────
    // TÂCHES — LISTE
    // ─────────────────────────────────────────────────────────────────────

    public function tasks(TodoProject $todoProject)
    {
        $this->checkAccess();
        $this->authorizeProject($todoProject);

        $tasks = $todoProject->allTasks()
            ->with(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date'])
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
            'title'            => 'required|string|max:500',
            'description'      => 'nullable|string|max:5000',
            'due_date'         => 'nullable|date',
            'priority'         => 'in:none,low,medium,high,urgent',
            'status'           => 'in:todo,in_progress,done',
            'parent_task_id'   => 'nullable|exists:todo_tasks,id',
            'assigned_to'      => 'nullable|exists:users,id',
            'label_ids'        => 'nullable|array',
            'label_ids.*'      => 'exists:todo_labels,id',
            'reservation_ids'  => 'nullable|array',
            'reservation_ids.*'=> 'exists:reservations,id',
            'reminder_at'      => 'nullable|date',
            'reminder_channel' => 'nullable|in:notification,email,both',
        ]);

        $data['project_id']  = $todoProject->id;
        $data['created_by']  = Auth::id();
        $data['position']    = $todoProject->allTasks()
            ->where('parent_task_id', $data['parent_task_id'] ?? null)
            ->max('position') + 1;

        $labelIds       = $data['label_ids'] ?? [];
        $reservationIds = $data['reservation_ids'] ?? [];
        unset($data['label_ids'], $data['reservation_ids']);

        $task = TodoTask::create($data);
        if ($labelIds) {
            $task->labels()->sync($labelIds);
        }
        if ($reservationIds) {
            $task->reservations()->sync($reservationIds);
        }

        $task->load(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date']);

        return response()->json($this->formatTask($task));
    }

    public function updateTask(Request $request, TodoTask $todoTask)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        $data = $request->validate([
            'title'            => 'sometimes|required|string|max:500',
            'description'      => 'nullable|string|max:5000',
            'due_date'         => 'nullable|date',
            'priority'         => 'sometimes|in:none,low,medium,high,urgent',
            'status'           => 'sometimes|in:todo,in_progress,done',
            'position'         => 'sometimes|integer',
            'assigned_to'      => 'nullable|exists:users,id',
            'label_ids'        => 'nullable|array',
            'label_ids.*'      => 'exists:todo_labels,id',
            'reservation_ids'  => 'nullable|array',
            'reservation_ids.*'=> 'exists:reservations,id',
            'reminder_at'      => 'nullable|date',
            'reminder_channel' => 'nullable|in:notification,email,both',
        ]);

        if (array_key_exists('label_ids', $data)) {
            $todoTask->labels()->sync($data['label_ids'] ?? []);
            unset($data['label_ids']);
        }
        if (array_key_exists('reservation_ids', $data)) {
            $todoTask->reservations()->sync($data['reservation_ids'] ?? []);
            unset($data['reservation_ids']);
        }

        // Réinitialiser reminder_sent_at si le rappel a changé
        if (array_key_exists('reminder_at', $data)) {
            $data['reminder_sent_at'] = null;
        }

        $todoTask->update($data);
        $todoTask->load(['labels:id,name,color', 'assignedTo:id,name', 'subtasks', 'reservations:id,title,start_date,end_date']);

        return response()->json($this->formatTask($todoTask));
    }

    public function destroyTask(TodoTask $todoTask)
    {
        $this->checkAccess();
        $this->authorizeProject($todoTask->project);

        $todoTask->delete();

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
        if (!Auth::user()->is_admin) abort(403);
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

    private function formatTask(TodoTask $t): array
    {
        return [
            'id'               => $t->id,
            'project_id'       => $t->project_id,
            'parent_task_id'   => $t->parent_task_id,
            'title'            => $t->title,
            'description'      => $t->description,
            'due_date'         => $t->due_date?->format('Y-m-d'),
            'reminder_at'      => $t->reminder_at?->format('Y-m-d\TH:i'),
            'reminder_channel' => $t->reminder_channel,
            'priority'         => $t->priority,
            'status'           => $t->status,
            'position'         => $t->position,
            'assigned_to'      => $t->assignedTo ? ['id' => $t->assignedTo->id, 'name' => $t->assignedTo->name] : null,
            'labels'           => $t->labels->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->values(),
            'subtasks'         => $t->subtasks->map(fn($s) => $this->formatTask($s))->values(),
            'reservations'     => ($t->relationLoaded('reservations') ? $t->reservations : collect())->map(fn($r) => [
                'id'         => $r->id,
                'title'      => $r->title,
                'start_date' => $r->start_date->format('d/m/Y'),
                'end_date'   => $r->end_date->format('d/m/Y'),
            ])->values(),
        ];
    }
}
