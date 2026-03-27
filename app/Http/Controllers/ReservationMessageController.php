<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReservationMessageController extends Controller
{
    /**
     * List messages for a reservation (JSON).
     * Marks unread messages as read for the current user.
     */
    public function index(Reservation $reservation)
    {
        Gate::authorize('view', $reservation);

        $isAdmin = Auth::user()->hasRole('admin');

        // Mark incoming messages as read
        if ($isAdmin) {
            $reservation->messages()
                ->where('read_by_admin', false)
                ->where('sender_id', '!=', Auth::id())
                ->update(['read_by_admin' => true]);
        } else {
            $reservation->messages()
                ->where('read_by_user', false)
                ->where('sender_id', '!=', Auth::id())
                ->update(['read_by_user' => true]);
        }

        return $reservation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'body'       => $m->body,
                'sender_id'  => $m->sender_id,
                'sender'     => ['id' => $m->sender->id, 'name' => $m->sender->name, 'is_admin' => $m->sender->hasRole('admin')],
                'created_at' => $m->created_at->format('d/m H:i'),
                'is_mine'    => $m->sender_id === Auth::id(),
            ]);
    }

    /**
     * Send a new message.
     */
    public function store(Request $request, Reservation $reservation)
    {
        Gate::authorize('view', $reservation);

        $request->validate(['body' => 'required|string|max:2000']);

        $isAdmin = Auth::user()->hasRole('admin');

        $msg = $reservation->messages()->create([
            'sender_id'    => Auth::id(),
            'body'         => $request->body,
            'read_by_admin' => $isAdmin,   // admin's own message = already "read" by admin
            'read_by_user'  => !$isAdmin,  // user's own message = already "read" by user
        ]);

        return response()->json([
            'id'         => $msg->id,
            'body'       => $msg->body,
            'sender_id'  => $msg->sender_id,
            'sender'     => ['id' => Auth::id(), 'name' => Auth::user()->name, 'is_admin' => (bool)$isAdmin],
            'created_at' => $msg->created_at->format('d/m H:i'),
            'is_mine'    => true,
        ], 201);
    }

    /**
     * Count unread messages for the current user on a reservation.
     */
    public function unread(Reservation $reservation)
    {
        Gate::authorize('view', $reservation);

        $isAdmin = Auth::user()->hasRole('admin');

        $count = $reservation->messages()
            ->where($isAdmin ? 'read_by_admin' : 'read_by_user', false)
            ->where('sender_id', '!=', Auth::id())
            ->count();

        return response()->json(['count' => $count]);
    }
}
