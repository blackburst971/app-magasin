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
            'task_id' => $n->data['task_id'] ?? null,
            'project_id' => $n->data['project_id'] ?? null,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
        ]);

        // NE PAS marquer comme lues automatiquement ici
        // Les notifications seront marquées comme lues individuellement
        // ou via markAllRead() quand l'utilisateur clique sur "Tout marquer comme lu"

        return response()->json($notifications);
    }

    /**
     * Retourne uniquement le nombre de notifications non lues (sans les marquer comme lues).
     * Utilisé pour le polling en arrière-plan.
     */
    public function unreadCount()
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications()->count(),
        ]);
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
