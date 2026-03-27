<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ScannerController extends Controller
{
    public function index()
    {
        return Inertia::render('Scanner/Index');
    }

    public function lookup(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $code = $request->code;

        // Try to find by asset_tag or by URL pattern /assets/{id}
        $asset = Asset::where('asset_tag', $code)->first();

        if (!$asset && preg_match('/\/assets\/(\d+)/', $code, $matches)) {
            $asset = Asset::find($matches[1]);
        }

        if (!$asset) {
            return response()->json(['error' => 'Équipement non trouvé pour le code: ' . $code], 404);
        }

        $activeReservation = Reservation::where('status', 'active')
            ->whereHas('assets', fn($q) => $q->where('assets.id', $asset->id))
            ->with('user')
            ->first();

        $imageUrl = $asset->image
            ? (str_starts_with($asset->image, 'http') ? $asset->image : Storage::url($asset->image))
            : null;

        return response()->json([
            'asset' => [
                'id'           => $asset->id,
                'name'         => $asset->name,
                'asset_tag'    => $asset->asset_tag,
                'status'       => $asset->status,
                'status_label' => $asset->status_label,
                'category'     => $asset->category?->name,
                'image_url'    => $imageUrl,
            ],
            'reservation' => $activeReservation ? [
                'id' => $activeReservation->id,
                'title' => $activeReservation->title,
                'user' => $activeReservation->user->name,
                'checkout_at' => $activeReservation->checkout_at?->format('d/m/Y H:i'),
            ] : null,
        ]);
    }

    public function returnAsset(Request $request)
    {
        $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'reservation_id' => 'nullable|exists:reservations,id',
        ]);

        $asset = Asset::findOrFail($request->asset_id);
        $asset->update(['status' => 'available']);

        if ($request->reservation_id) {
            $reservation = Reservation::find($request->reservation_id);
            $reservation?->assets()->updateExistingPivot($asset->id, ['returned_at' => now()]);

            $allReturned = $reservation?->assets()
                ->wherePivotNull('returned_at')
                ->doesntExist();

            if ($allReturned) {
                $reservation?->update(['status' => 'returned', 'return_at' => now()]);
            }
        }

        AssetMovement::create([
            'asset_id' => $asset->id,
            'reservation_id' => $request->reservation_id,
            'user_id' => $request->user()->id,
            'action' => 'return',
            'notes' => 'Retour via scanner',
        ]);

        return response()->json(['success' => true, 'message' => "{$asset->name} marqué comme retourné."]);
    }
}
