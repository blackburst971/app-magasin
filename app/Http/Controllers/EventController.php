<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with(['responsibleUser', 'reservations.assets'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn($e) => $this->formatEvent($e));

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Events/Index', [
            'events' => $events,
            'users'  => $users,
        ]);
    }

    public function show(Event $event)
    {
        $event->load(['responsibleUser', 'createdBy', 'reservations.assets', 'reservations.user']);

        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('Events/Show', [
            'event' => $this->formatEvent($event),
            'users' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'location'            => 'nullable|string|max:255',
            'color'               => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'responsible_user_id' => 'nullable|exists:users,id',
        ]);

        $event = Event::create(array_merge($data, [
            'created_by_user_id' => Auth::id(),
            'color' => $data['color'] ?? '#6366f1',
        ]));

        return redirect()->route('events.show', $event)->with('success', 'Événement créé.');
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'location'            => 'nullable|string|max:255',
            'color'               => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'start_date'          => 'required|date',
            'end_date'            => 'required|date|after_or_equal:start_date',
            'responsible_user_id' => 'nullable|exists:users,id',
        ]);

        $event->update($data);
        return back()->with('success', 'Événement mis à jour.');
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return redirect()->route('events.index')->with('success', 'Événement supprimé.');
    }

    /**
     * Créer une réservation liée à cet événement.
     * Fonctionne exactement comme une réservation classique.
     */
    public function createReservation(Request $request, Event $event)
    {
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $reservation = Reservation::create([
            'event_id'   => $event->id,
            'title'      => $data['title'] ?? $event->title,
            'user_id'    => $data['user_id'] ?? Auth::id(),
            'start_date' => $event->start_date,
            'end_date'   => $event->end_date,
            'status'     => 'approved', // directement approuvée car admin
        ]);

        return back()->with('success', 'Réservation créée pour l\'événement.');
    }

    /**
     * API pour le calendrier — retourne les événements sous forme d'events FullCalendar.
     */
    public function calendarEvents(Request $request)
    {
        $events = Event::with('responsibleUser')
            ->when($request->start, fn($q) => $q->where('end_date', '>=', $request->start))
            ->when($request->end,   fn($q) => $q->where('start_date', '<=', $request->end))
            ->get();

        return response()->json($events->map(fn($e) => [
            'id'    => 'event-' . $e->id,
            'title' => '🎯 ' . $e->title . ($e->responsibleUser ? ' (' . $e->responsibleUser->name . ')' : ''),
            'start' => $e->start_date->toIso8601String(),
            'end'   => $e->end_date->toIso8601String(),
            'backgroundColor' => $e->color,
            'borderColor'     => $e->color,
            'extendedProps'   => [
                'type'        => 'event',
                'description' => $e->description,
                'location'    => $e->location,
                'event_id'    => $e->id,
            ],
        ]));
    }

    private function formatEvent(Event $event): array
    {
        return [
            'id'               => $event->id,
            'title'            => $event->title,
            'description'      => $event->description,
            'location'         => $event->location,
            'color'            => $event->color,
            'start_date'       => $event->start_date->format('Y-m-d\TH:i'),
            'end_date'         => $event->end_date->format('Y-m-d\TH:i'),
            'start_date_label' => $event->start_date->format('d/m/Y H:i'),
            'end_date_label'   => $event->end_date->format('d/m/Y H:i'),
            'responsible_user' => $event->responsibleUser
                ? ['id' => $event->responsibleUser->id, 'name' => $event->responsibleUser->name]
                : null,
            'created_by'       => $event->createdBy?->name,
            'reservations'     => $event->relationLoaded('reservations')
                ? $event->reservations->map(fn($r) => [
                    'id'         => $r->id,
                    'title'      => $r->title,
                    'status'     => $r->status,
                    'user_name'  => $r->user?->name,
                    'assets_count' => $r->assets->count(),
                    'checked_out'  => $r->assets->filter(fn($a) => $a->pivot->checkout_at)->count(),
                ])->values()
                : [],
        ];
    }
}
