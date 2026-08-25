<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TodoTask;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GoogleCalendarController extends Controller
{
    /**
     * Déconnecte le compte de service Google Calendar (supprime la clé JSON).
     */
    public function disconnect()
    {
        GoogleCalendarService::disconnect();
        return back()->with('success', 'Compte de service Google Calendar déconnecté.');
    }

    /**
     * Diagnostic pas-à-pas de la connexion Google Calendar.
     * Retourne JSON { ok: bool, message: string } avec détail de l'étape qui échoue.
     */
    public function testConnection()
    {
        if (!GoogleCalendarService::isConnected()) {
            return response()->json(['ok' => false, 'message' => 'Aucun compte de service configuré. Collez la clé JSON et sauvegardez d\'abord.']);
        }

        $result = GoogleCalendarService::diagnose();
        return response()->json($result);
    }

    /**
     * Toggle sync_to_calendar sur une tâche Todo.
     * Crée ou supprime l'événement Google Calendar selon la valeur.
     */
    public function toggleTaskSync(TodoTask $task)
    {
        $user = Auth::user();
        // Vérification d'accès : propriétaire, assigné, ou admin
        if (!$user->hasRole('admin')
            && $task->created_by !== $user->id
            && $task->assigned_to !== $user->id) {
            abort(403);
        }

        $newValue = !$task->sync_to_calendar;
        $eventId  = $task->google_event_id;

        if ($newValue) {
            // Activer la sync → créer l'événement si calendrier connecté
            if (GoogleCalendarService::isEnabled() && GoogleCalendarService::isConnected()) {
                $eventId = GoogleCalendarService::syncTask($task);
            }
            $task->update(['sync_to_calendar' => true, 'google_event_id' => $eventId]);
        } else {
            // Désactiver → supprimer l'événement
            if ($task->google_event_id) {
                GoogleCalendarService::deleteEvent($task->google_event_id);
            }
            $task->update(['sync_to_calendar' => false, 'google_event_id' => null]);
        }

        return response()->json([
            'sync_to_calendar' => $task->fresh()->sync_to_calendar,
            'google_event_id'  => $task->fresh()->google_event_id,
            'calendar_enabled' => GoogleCalendarService::isEnabled() && GoogleCalendarService::isConnected(),
        ]);
    }
}
