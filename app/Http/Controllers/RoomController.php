<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomReservation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request): Response
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');

        $rooms = Room::orderBy('name')->get()->map(fn($r) => [
            'id'           => $r->id,
            'name'         => $r->name,
            'location'     => $r->location,
            'capacity'     => $r->capacity,
            'description'  => $r->description,
            'is_reservable'=> $r->is_reservable,
            'color'        => $r->color,
        ]);

        // Reservations : admin voit tout, utilisateur voit les siennes
        $query = RoomReservation::with(['room', 'user', 'approvedBy'])
            ->orderBy('start_date', 'desc');

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        $reservations = $query->get()->map(fn($r) => [
            'id'            => $r->id,
            'room'          => ['id' => $r->room->id, 'name' => $r->room->name, 'color' => $r->room->color],
            'user'          => ['id' => $r->user->id, 'name' => $r->user->name],
            'title'         => $r->title,
            'start_date'    => $r->start_date->format('d/m/Y H:i'),
            'end_date'      => $r->end_date->format('d/m/Y H:i'),
            'start_date_raw'=> $r->start_date->toISOString(),
            'end_date_raw'  => $r->end_date->toISOString(),
            'status'        => $r->status,
            'status_label'  => $r->status_label,
            'status_color'  => $r->status_color,
            'notes'         => $r->notes,
            'admin_notes'   => $r->admin_notes,
            'approved_by'   => $r->approvedBy?->name,
            'approved_at'   => $r->approved_at?->format('d/m/Y H:i'),
        ]);

        $users = $isAdmin ? User::orderBy('name')->get(['id', 'name', 'email']) : collect();

        return Inertia::render('Rooms/Index', [
            'rooms'        => $rooms,
            'reservations' => $reservations,
            'isAdmin'      => $isAdmin,
            'users'        => $users,
        ]);
    }

    // ── CRUD Salles (admin only) ───────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'location'     => 'nullable|string|max:255',
            'capacity'     => 'nullable|integer|min:1',
            'description'  => 'nullable|string|max:1000',
            'is_reservable'=> 'boolean',
            'color'        => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        Room::create($data);

        return back()->with('success', 'Salle creee avec succes.');
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'location'     => 'nullable|string|max:255',
            'capacity'     => 'nullable|integer|min:1',
            'description'  => 'nullable|string|max:1000',
            'is_reservable'=> 'boolean',
            'color'        => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $room->update($data);

        return back()->with('success', 'Salle mise a jour.');
    }

    public function destroy(Room $room)
    {
        $room->delete();
        return back()->with('success', 'Salle supprimee.');
    }

    // ── Reservations de salles ────────────────────────────────────────────────

    public function storeReservation(Request $request)
    {
        $isAdmin = $request->user()->hasRole('admin');

        $rules = [
            'room_id'    => 'required|exists:rooms,id',
            'title'      => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'notes'      => 'nullable|string|max:1000',
        ];
        if ($isAdmin) {
            $rules['user_id'] = 'nullable|exists:users,id';
            $rules['status']  = 'nullable|in:pending,approved,cancelled,rejected';
        }

        $data = $request->validate($rules, [
            'end_date.after' => 'La date de fin doit etre posterieure a la date de debut.',
        ]);

        $room  = Room::findOrFail($data['room_id']);
        $start = new \DateTime($data['start_date']);
        $end   = new \DateTime($data['end_date']);

        if (!$room->is_reservable) {
            return back()->withErrors(['room_id' => 'Cette salle n\'est pas disponible a la reservation.']);
        }

        if (!$room->isAvailableForPeriod($start, $end)) {
            return back()->withErrors(['start_date' => 'Cette salle est deja reservee pour cette periode.']);
        }

        $userId = ($isAdmin && !empty($data['user_id'])) ? $data['user_id'] : $request->user()->id;
        $status = ($isAdmin && !empty($data['status'])) ? $data['status'] : 'pending';

        // Si admin cree directement avec statut approuve
        $approvedBy = null;
        $approvedAt = null;
        if ($status === 'approved') {
            $approvedBy = $request->user()->id;
            $approvedAt = now();
        }

        RoomReservation::create([
            'room_id'     => $data['room_id'],
            'user_id'     => $userId,
            'title'       => $data['title'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'notes'       => $data['notes'] ?? null,
            'status'      => $status,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
        ]);

        return back()->with('success', 'Reservation de salle creee.');
    }

    public function cancelReservation(Request $request, RoomReservation $roomReservation)
    {
        $user = $request->user();
        if ($roomReservation->user_id !== $user->id && !$user->hasRole('admin')) {
            abort(403);
        }

        if (!in_array($roomReservation->status, ['pending', 'approved'])) {
            return back()->withErrors(['status' => 'Cette reservation ne peut pas etre annulee dans son etat actuel.']);
        }

        $roomReservation->update(['status' => 'cancelled']);

        return back()->with('success', 'Reservation annulee.');
    }

    public function approveReservation(Request $request, RoomReservation $roomReservation)
    {
        if ($roomReservation->status !== 'pending') {
            return back()->withErrors(['status' => 'Cette reservation ne peut pas etre approuvee dans son etat actuel.']);
        }

        $roomReservation->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Reservation approuvee.');
    }

    public function rejectReservation(Request $request, RoomReservation $roomReservation)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:500']);

        if ($roomReservation->status !== 'pending') {
            return back()->withErrors(['status' => 'Cette reservation ne peut pas etre refusee dans son etat actuel.']);
        }

        $roomReservation->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', 'Reservation refusee.');
    }

    /**
     * API JSON pour le calendrier — salles reservees.
     */
    public function calendarEvents(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');

        $query = RoomReservation::with(['room', 'user'])
            ->whereNotIn('status', ['cancelled', 'rejected']);

        if (!$isAdmin) {
            $query->where('user_id', $user->id);
        }

        $events = $query->get()->map(fn($r) => [
            'id'              => 'room-' . $r->id,
            'title'           => $isAdmin
                ? "[{$r->user->name}] {$r->room->name} — {$r->title}"
                : "{$r->room->name} — {$r->title}",
            'start'           => $r->start_date->toISOString(),
            'end'             => $r->end_date->toISOString(),
            'backgroundColor' => $r->room->color,
            'borderColor'     => $r->room->color,
            'extendedProps'   => [
                'type'         => 'room',
                'status'       => $r->status,
                'status_label' => $r->status_label,
                'user'         => $r->user->name,
                'room'         => $r->room->name,
                'room_url'     => route('rooms.index'),
            ],
        ]);

        return response()->json($events);
    }
}
