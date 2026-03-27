<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ReturnReminderNotification;
use Illuminate\Console\Command;

class SendReturnReminders extends Command
{
    protected $signature   = 'app:send-return-reminders';
    protected $description = 'Envoie des rappels aux admins pour les retours d\'équipements imminents.';

    public function handle(): int
    {
        // Vérifier que la fonctionnalité est activée
        $enabled = Setting::get('return_reminder_enabled', 'true') === 'true';
        if (!$enabled) {
            $this->info('Rappels de retour désactivés — rien à faire.');
            return self::SUCCESS;
        }

        $minutes = (int) Setting::get('return_reminder_minutes', '30');

        if ($minutes <= 0) {
            $this->warn('Durée de rappel invalide (return_reminder_minutes).');
            return self::FAILURE;
        }

        $now        = now();
        $windowEnd  = $now->copy()->addMinutes($minutes);

        // Réservations actives dont le retour est dans la fenêtre et dont le rappel n'a pas encore été envoyé
        $reservations = Reservation::with(['user', 'assets'])
            ->where('status', 'active')
            ->where('end_date', '>', $now)           // pas encore en retard
            ->where('end_date', '<=', $windowEnd)    // dans la fenêtre de rappel
            ->whereNull('return_reminder_sent_at')   // rappel pas encore envoyé
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('Aucun rappel à envoyer.');
            return self::SUCCESS;
        }

        $admins = User::role('admin')->get();

        foreach ($reservations as $reservation) {
            // Notifier tous les admins
            foreach ($admins as $admin) {
                try {
                    $admin->notify(new ReturnReminderNotification($reservation, $minutes));
                } catch (\Exception $e) {
                    $this->error("Erreur notification admin {$admin->id} : " . $e->getMessage());
                }
            }

            // Marquer le rappel comme envoyé pour éviter les doublons
            $reservation->update(['return_reminder_sent_at' => now()]);

            $this->line("✅ Rappel envoyé pour réservation #{$reservation->id} — {$reservation->title}");
        }

        $this->info("Rappels envoyés pour {$reservations->count()} réservation(s).");
        return self::SUCCESS;
    }
}
