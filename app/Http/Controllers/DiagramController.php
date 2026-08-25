<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DiagramController extends Controller
{
    public function index()
    {
        return Inertia::render('Diagrams/Index', [
            'diagrams' => Diagram::with('creator')
                ->latest()
                ->get()
                ->map(fn($d) => [
                    'id'          => $d->id,
                    'name'        => $d->name,
                    'description' => $d->description,
                    'data'        => $d->data,
                    'created_by'  => $d->creator?->name,
                    'updated_at'  => $d->updated_at->format('d/m/Y H:i'),
                ]),
            'events' => Event::orderBy('start_date', 'desc')
                ->get(['id', 'title', 'color'])
                ->map(fn($e) => ['id' => $e->id, 'title' => $e->title, 'color' => $e->color]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'data'        => 'nullable|array',
        ]);

        $diagram = Diagram::create([...$data, 'created_by' => $request->user()->id]);

        return response()->json([
            'id'          => $diagram->id,
            'name'        => $diagram->name,
            'description' => $diagram->description,
            'data'        => $diagram->data,
            'updated_at'  => $diagram->updated_at->format('d/m/Y H:i'),
            'created_by'  => $request->user()->name,
        ], 201);
    }

    public function update(Request $request, Diagram $diagram)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'data'        => 'nullable|array',
        ]);

        $diagram->update($data);

        return response()->json([
            'id'          => $diagram->id,
            'name'        => $diagram->name,
            'description' => $diagram->description,
            'data'        => $diagram->data,
            'updated_at'  => $diagram->updated_at->format('d/m/Y H:i'),
        ]);
    }

    public function destroy(Diagram $diagram)
    {
        $diagram->delete();
        return response()->json(['ok' => true]);
    }

    /** Attacher un diagramme à un événement */
    public function attachEvent(Request $request, Diagram $diagram)
    {
        $request->validate(['event_id' => 'required|exists:events,id']);
        $diagram->events()->syncWithoutDetaching([$request->event_id]);
        return response()->json(['ok' => true]);
    }

    /** Détacher un diagramme d'un événement */
    public function detachEvent(Diagram $diagram, Event $event)
    {
        $diagram->events()->detach($event->id);
        return response()->json(['ok' => true]);
    }
}
