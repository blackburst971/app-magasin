<?php

namespace App\Console\Commands;

use App\Mail\CheckoutSummary;
use App\Models\AssetMovement;
use App\Models\Preparation;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutoCheckoutPreparations extends Command
{
    protected $signature = 'app:auto-checkout-preparations';
    protected $description = 'Valide automatiquement les préparations dont l\'heure de remise automatique est atteinte';

    public function handle(): void
    {
        $due = Preparation::with(['assets', 'user', 'preparationAssets.kit'])
            ->where('status', 'preparing')
            ->whereNotNull('auto_checkout_at')
            ->where('auto_checkout_at', '<=', now())
            ->get();

        if ($due->isEmpty()) return;

        foreach ($due as $preparation) {
            try {
                $this->processPreparation($preparation);
                Log::info("AutoCheckout: Préparation #{$preparation->id} validée automatiquement.");
            } catch (\Throwable $e) {
                Log::error("AutoCheckout: Erreur préparation #{$preparation->id}: {$e->getMessage()}");
            }
        }
    }

    private function processPreparation(Preparation $preparation): void
    {
        if ($preparation->assets()->count() === 0) {
            // Aucun équipement : annuler silencieusement
            $preparation->update(['status' => 'cancelled']);
            return;
        }

        $kitIds = $preparation->preparationAssets->pluck('kit_id')->filter()->unique()->toArray();

        if ($preparation->reservation_id) {
            $reservation = Reservation::findOrFail($preparation->reservation_id);
            $reservation->update([
                'status'      => 'active',
                'checkout_at' => now(),
            ]);

            foreach ($preparation->assets as $asset) {
                if (!$reservation->assets()->where('assets.id', $asset->id)->exists()) {
                    $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
                } else {
                    $reservation->assets()->updateExistingPivot($asset->id, ['checkout_at' => now()]);
                }
                $asset->update([
                    'status'                 => 'checked_out',
                    'checked_out_to_user_id' => $preparation->user_id,
                    'expected_return_at'     => $preparation->planned_end_at,
                ]);
                AssetMovement::create([
                    'asset_id'       => $asset->id,
                    'reservation_id' => $reservation->id,
                    'user_id'        => $preparation->prepared_by,
                    'action'         => 'checkout',
                    'notes'          => "Sortie automatique à l'heure de remise — Préparation #{$preparation->id}",
                ]);
            }
        } else {
            $reservation = Reservation::create([
                'user_id'     => $preparation->user_id,
                'title'       => $preparation->title,
                'start_date'  => $preparation->planned_start_at,
                'end_date'    => $preparation->planned_end_at,
                'status'      => 'active',
                'notes'       => $preparation->notes,
                'approved_by' => $preparation->prepared_by,
                'approved_at' => now(),
                'checkout_at' => now(),
            ]);

            foreach ($preparation->assets as $asset) {
                $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
                $asset->update([
                    'status'                 => 'checked_out',
                    'checked_out_to_user_id' => $preparation->user_id,
                    'expected_return_at'     => $preparation->planned_end_at,
                ]);
                AssetMovement::create([
                    'asset_id'       => $asset->id,
                    'reservation_id' => $reservation->id,
                    'user_id'        => $preparation->prepared_by,
                    'action'         => 'checkout',
                    'notes'          => "Sortie automatique à l'heure de remise — Préparation #{$preparation->id}",
                ]);
            }

            $preparation->reservation_id = $reservation->id;
        }

        $preparation->update([
            'status'       => 'validated',
            'validated_at' => now(),
            'validated_by' => $preparation->prepared_by,
        ]);

        // Recalculer les kits
        foreach ($kitIds as $kitId) {
            $kit = \App\Models\Kit::with('assets')->find($kitId);
            if (!$kit) continue;
            $hasInPrep     = $kit->assets->contains(fn($a) => $a->status === 'en_preparation');
            $hasCheckedOut = $kit->assets->contains(fn($a) => $a->status === 'checked_out');
            $kit->update(['status' => ($hasInPrep || $hasCheckedOut) ? 'unavailable' : 'available']);
        }

        // Envoyer le récapitulatif de sortie si demandé
        if ($preparation->send_checkout_mail) {
            try {
                $reservation->load(['user', 'assets.category']);
                Mail::to($preparation->user->email)->send(new CheckoutSummary($reservation));
            } catch (\Exception $e) {}
        }
    }
}
