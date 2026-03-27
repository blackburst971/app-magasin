<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ReservationWaitlist;
use App\Notifications\WaitlistAssetAvailableNotification;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'desired_start' => 'nullable|date',
            'desired_end'   => 'nullable|date|after_or_equal:desired_start',
            'notes'         => 'nullable|string|max:500',
        ]);

        ReservationWaitlist::updateOrCreate(
            ['asset_id' => $asset->id, 'user_id' => $request->user()->id],
            array_merge($data, ['notified_at' => null])
        );

        return back()->with('success', "Vous avez rejoint la liste d'attente pour « {$asset->name} ».");
    }

    public function destroy(Request $request, Asset $asset)
    {
        ReservationWaitlist::where('asset_id', $asset->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', "Vous avez quitté la liste d'attente.");
    }

    /**
     * Notify all waitlisted users for an asset that it's now available.
     * Called when an asset returns to 'available' status.
     */
    public static function notifyWaitlist(Asset $asset): void
    {
        $waitlisted = ReservationWaitlist::with('user')
            ->where('asset_id', $asset->id)
            ->whereNull('notified_at')
            ->get();

        foreach ($waitlisted as $entry) {
            $entry->user->notify(new WaitlistAssetAvailableNotification($asset));
            $entry->update(['notified_at' => now()]);
        }
    }
}
