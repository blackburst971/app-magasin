<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()->notifications()->latest()->take(30)->get()->map(fn($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'general',
            'message' => $n->data['message'] ?? '',
            'reservation_id' => $n->data['reservation_id'] ?? null,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
        ]);

        Auth::user()->unreadNotifications->markAsRead();

        return response()->json($notifications);
    }

    public function markRead(string $id)
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead()
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
