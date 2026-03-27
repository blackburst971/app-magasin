<?php

namespace App\Console\Commands;

use App\Mail\ReservationAutoCancelled;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CancelExpiredApprovedReservations extends Command
{
    protected $signature   = 'app:cancel-expired-reservations';
    protected $description = 'Annule automatiquement les réservations approuvées dont le retrait n\'a pas été effectué dans le délai imparti.';

    public function handle(): int
    {
        $enabled = Setting::get('auto_cancel_enabled', 'false') === 'true';
        if (!$enabled) {
            $this->info('Annulation automatique désactivée — rien à faire.');
            return self::SUCCESS;
        }

        $hours = (int) Setting::get('auto_cancel_hours', '24');
        if ($hours <= 0) {
            $this->warn('Délai invalide (auto_cancel_hours).');
            return self::FAILURE;
        }

        // Réservations approuvées mais non retirées depuis plus de N heures
        $reservations = Reservation::with(['user', 'assets'])
            ->where('status', 'approved')
            ->where('approved_at', '<', now()->subHours($hours))
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('Aucune réservation à annuler automatiquement.');
            return self::SUCCESS;
        }

        foreach ($reservations as $reservation) {
            // Annuler la réservation
            $reservation->update([
                'status'      => 'cancelled',
                'admin_notes' => "Annulation automatique : retrait non effectué dans les {$hours}h suivant l'approbation.",
            ]);

            // Libérer les équipements réservés
            foreach ($reservation->assets as $asset) {
                if ($asset->status === 'reserved') {
                    $asset->update(['status' => 'available']);
                }
            }

            // Notifier l'utilisateur par email
            if ($reservation->user?->email) {
                try {
                    Mail::to($reservation->user->email)
                        ->send(new ReservationAutoCancelled($reservation, $hours));
                } catch (\Exception $e) {
                    $this->error("Erreur email pour réservation #{$reservation->id} : " . $e->getMessage());
                }
            }

            $this->line("✅ Réservation #{$reservation->id} annulée — {$reservation->title}");
        }

        $this->info("{$reservations->count()} réservation(s) annulée(s) automatiquement.");
        return self::SUCCESS;
    }
}
