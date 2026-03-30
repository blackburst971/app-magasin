<?php

namespace App\Http\Controllers;

use App\Mail\CheckoutSummary;
use App\Models\CheckoutForm;
use App\Models\Reservation;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AttributionController extends Controller
{
    public function index()
    {
        $reservations = Reservation::with(['user', 'assets'])
            ->where('status', 'approved')
            ->orderBy('start_date')
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'title' => $r->title,
                'user' => [
                    'id' => $r->user->id,
                    'name' => $r->user->name,
                    'email' => $r->user->email,
                ],
                'start_date' => $r->start_date,
                'end_date' => $r->end_date,
                'assets' => $r->assets->map(fn($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'status' => $a->status,
                    'qr_code' => $a->qr_code,
                    'checkout_at' => $a->pivot->checkout_at,
                ]),
                'all_checked_out' => $r->assets->every(fn($a) => $a->pivot->checkout_at !== null),
            ]);

        return Inertia::render('Attribution/Index', [
            'reservations' => $reservations,
            'users'        => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function scanCheckout(Request $request)
    {
        $request->validate([
            'asset_tag'          => 'required|string',
            'reservation_id'     => 'required|exists:reservations,id',
            'send_checkout_email' => 'nullable|boolean',
        ]);

        $reservation = Reservation::with(['assets', 'assets.equipmentModel'])->findOrFail($request->reservation_id);
        $asset = Asset::with('equipmentModel')->where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }

        $pivotAsset = $reservation->assets->find($asset->id);

        if (!$pivotAsset) {
            // ── Recherche d'un équipement de modèle équivalent ────────────────
            if ($asset->equipment_model_id) {
                $equivalentReservationAsset = $reservation->assets
                    ->filter(fn($a) => $a->equipment_model_id === $asset->equipment_model_id && !$a->pivot->checkout_at)
                    ->first();

                if ($equivalentReservationAsset) {
                    return response()->json([
                        'equivalent_offer' => true,
                        'scanned_asset'    => ['id' => $asset->id, 'name' => $asset->name, 'asset_tag' => $asset->asset_tag],
                        'original_asset'   => ['id' => $equivalentReservationAsset->id, 'name' => $equivalentReservationAsset->name, 'asset_tag' => $equivalentReservationAsset->asset_tag],
                        'model_name'       => $asset->equipmentModel?->name ?? 'Même modèle',
                    ], 200);
                }
            }

            // Proposer d'ajouter cet equipement a la reservation
            return response()->json([
                'add_to_reservation_offer' => true,
                'scanned_asset' => [
                    'id'        => $asset->id,
                    'name'      => $asset->name,
                    'asset_tag' => $asset->asset_tag,
                ],
            ], 200);
        }

        if ($pivotAsset->pivot->checkout_at) {
            // Vérifier si c'est un accessoire auto-sorti (parent_asset_id non null)
            if ($asset->parent_asset_id) {
                return response()->json([
                    'success' => true,
                    'already_checked_out_accessory' => true,
                    'asset' => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
                ]);
            }
            return response()->json(['error' => "Cet équipement a déjà été scanné"], 409);
        }

        // Mark as checked out
        $reservation->assets()->updateExistingPivot($asset->id, ['checkout_at' => now()]);
        $asset->update([
            'status' => 'checked_out',
            'checked_out_to_user_id' => $reservation->user_id,
            'expected_return_at' => $reservation->end_date,
        ]);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id' => Auth::id(),
            'action' => 'checkout',
            'notes' => "Attribution via scan par " . Auth::user()->name,
        ]);

        // Auto-checkout des accessoires liés (parent_asset_id)
        $asset->load('accessories');
        foreach ($asset->accessories as $accessory) {
            if ($accessory->status === 'checked_out') continue;
            if (!$reservation->assets->contains($accessory->id)) {
                $reservation->assets()->attach($accessory->id, ['checkout_at' => now()]);
            } else {
                $reservation->assets()->updateExistingPivot($accessory->id, ['checkout_at' => now()]);
            }
            $accessory->update([
                'status'                 => 'checked_out',
                'checked_out_to_user_id' => $reservation->user_id,
                'expected_return_at'     => $reservation->end_date,
            ]);
            AssetMovement::create([
                'asset_id'       => $accessory->id,
                'reservation_id' => $reservation->id,
                'user_id'        => Auth::id(),
                'action'         => 'checkout',
                'notes'          => "Accessoire sorti automatiquement avec {$asset->name}",
            ]);
        }

        // If all assets checked out, mark reservation as active
        $reservation->refresh();
        $checkoutEmailSent = false;
        if ($reservation->assets->every(fn($a) => $a->pivot->checkout_at !== null)) {
            $reservation->update(['status' => 'active', 'checkout_at' => now()]);
            if ($request->boolean('send_checkout_email')) {
                try {
                    Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation->fresh()));
                    $checkoutEmailSent = true;
                } catch (\Exception $e) {}
            }
        }

        return response()->json([
            'success'             => true,
            'asset'               => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
            'checkout_email_sent' => $checkoutEmailSent,
        ]);
    }

    public function manualCheckout(Request $request)
    {
        $request->validate([
            'reservation_id'      => 'required|exists:reservations,id',
            'asset_id'            => 'required|exists:assets,id',
            'send_checkout_email' => 'nullable|boolean',
        ]);

        $reservation = Reservation::with('assets')->findOrFail($request->reservation_id);
        $asset = Asset::findOrFail($request->asset_id);

        if ($reservation->status !== 'approved') {
            return response()->json(['error' => "La réservation n'est pas en statut approuvé"], 422);
        }

        $pivotAsset = $reservation->assets->find($asset->id);
        if (!$pivotAsset) {
            return response()->json(['error' => "Cet équipement n'appartient pas à cette réservation"], 422);
        }

        if ($pivotAsset->pivot->checkout_at) {
            return response()->json(['error' => "Cet équipement a déjà été attribué"], 409);
        }

        // Mark as checked out
        $reservation->assets()->updateExistingPivot($asset->id, ['checkout_at' => now()]);
        $asset->update([
            'status'                 => 'checked_out',
            'checked_out_to_user_id' => $reservation->user_id,
            'expected_return_at'     => $reservation->end_date,
        ]);

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'checkout',
            'notes'          => "Attribution manuelle sans scan — Réservation #{$reservation->id}",
        ]);

        // If all assets checked out → mark reservation active
        $reservation->refresh();
        $checkoutEmailSent = false;
        if ($reservation->assets->every(fn($a) => $a->pivot->checkout_at !== null)) {
            $reservation->update(['status' => 'active', 'checkout_at' => now()]);
            if ($request->boolean('send_checkout_email')) {
                try {
                    Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation->fresh()));
                    $checkoutEmailSent = true;
                } catch (\Exception $e) {}
            }
        }

        return response()->json([
            'success'             => true,
            'asset'               => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
            'checkout_email_sent' => $checkoutEmailSent,
        ]);
    }

    public function scanMultiple(Request $request)
    {
        $request->validate([
            'asset_tags'          => 'required|array|min:1',
            'asset_tags.*'        => 'string',
            'reservation_id'      => 'required|exists:reservations,id',
            'send_checkout_email' => 'nullable|boolean',
        ]);

        $reservation = Reservation::with('assets')->findOrFail($request->reservation_id);

        $results = [];
        $allCheckedOut = false;

        foreach ($request->asset_tags as $tag) {
            $tag = trim($tag);
            if (!$tag) continue;

            $asset = Asset::where('asset_tag', $tag)->first();

            if (!$asset) {
                $results[] = ['tag' => $tag, 'type' => 'error', 'message' => "Équipement introuvable : {$tag}"];
                continue;
            }

            // Refresh pivot
            $reservation->load('assets');
            $pivotAsset = $reservation->assets->find($asset->id);

            if (!$pivotAsset) {
                $results[] = ['tag' => $tag, 'type' => 'error', 'message' => "N'appartient pas à cette réservation : {$tag}"];
                continue;
            }

            if ($pivotAsset->pivot->checkout_at) {
                $results[] = ['tag' => $tag, 'type' => 'warning', 'message' => "Déjà scanné : {$tag}"];
                continue;
            }

            // Mark as checked out
            $reservation->assets()->updateExistingPivot($asset->id, ['checkout_at' => now()]);
            $asset->update([
                'status' => 'checked_out',
                'checked_out_to_user_id' => $reservation->user_id,
                'expected_return_at' => $reservation->end_date,
            ]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id' => Auth::id(),
                'action' => 'checkout',
                'notes' => "Attribution via multi-scan par " . Auth::user()->name,
            ]);

            $results[] = ['tag' => $tag, 'type' => 'success', 'message' => "{$asset->name} ({$tag}) — attribué", 'asset_name' => $asset->name];
        }

        // Check if all checked out
        $reservation->refresh();
        $checkoutEmailSent = false;
        if ($reservation->assets->every(fn($a) => $a->pivot->checkout_at !== null)) {
            $reservation->update(['status' => 'active', 'checkout_at' => now()]);
            $allCheckedOut = true;
            if ($request->boolean('send_checkout_email')) {
                try {
                    Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation->fresh()));
                    $checkoutEmailSent = true;
                } catch (\Exception $e) {}
            }
        }

        return response()->json([
            'success'             => true,
            'results'             => $results,
            'all_checked_out'     => $allCheckedOut,
            'checkout_email_sent' => $checkoutEmailSent,
        ]);
    }

    /**
     * Ajouter un equipement hors-reservation et l'attribuer immediatement.
     */
    public function addExtraAsset(Request $request)
    {
        $request->validate([
            'reservation_id'      => 'required|exists:reservations,id',
            'asset_id'            => 'required|exists:assets,id',
            'send_checkout_email' => 'nullable|boolean',
        ]);

        $reservation = Reservation::with('assets')->findOrFail($request->reservation_id);
        $asset       = Asset::findOrFail($request->asset_id);

        if ($reservation->status !== 'approved') {
            return response()->json(['error' => "La reservation n'est pas en statut approuve"], 422);
        }

        if ($reservation->assets->find($asset->id)) {
            return response()->json(['error' => "Cet equipement est deja dans la reservation"], 409);
        }

        // Attacher + checker out immediatement
        $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
        $asset->update([
            'status'                 => 'checked_out',
            'checked_out_to_user_id' => $reservation->user_id,
            'expected_return_at'     => $reservation->end_date,
        ]);

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'checkout',
            'notes'          => "Ajout hors-reservation et attribution par " . Auth::user()->name,
        ]);

        // Verifier si tout est sorti
        $reservation->refresh();
        $checkoutEmailSent = false;
        if ($reservation->assets->every(fn($a) => $a->pivot->checkout_at !== null)) {
            $reservation->update(['status' => 'active', 'checkout_at' => now()]);
            if ($request->boolean('send_checkout_email')) {
                try {
                    Mail::to($reservation->user->email)->send(new \App\Mail\CheckoutSummary($reservation->fresh()));
                    $checkoutEmailSent = true;
                } catch (\Exception $e) {}
            }
        }

        return response()->json([
            'success'             => true,
            'asset'               => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
            'checkout_email_sent' => $checkoutEmailSent,
        ]);
    }

    /**
     * Valider un tag avant de l'ajouter à la liste mode libre.
     */
    public function validateTag(Request $request)
    {
        $request->validate(['asset_tag' => 'required|string']);

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();
        if (!$asset) {
            return response()->json(['error' => "Équipement introuvable : {$request->asset_tag}"], 404);
        }
        if ($asset->status === 'checked_out') {
            return response()->json(['error' => "{$asset->name} ({$asset->asset_tag}) est déjà sorti"], 409);
        }
        return response()->json([
            'valid' => true,
            'asset' => ['id' => $asset->id, 'name' => $asset->name, 'asset_tag' => $asset->asset_tag],
        ]);
    }

    /**
     * Attribution libre en lot : crée une réservation active avec plusieurs équipements.
     */
    public function freeBulkCheckout(Request $request)
    {
        $request->validate([
            'asset_tags'          => 'required|array|min:1',
            'asset_tags.*'        => 'required|string',
            'user_id'             => 'required|exists:users,id',
            'end_date'            => 'required|date|after:now',
            'title'               => 'nullable|string|max:255',
            'send_checkout_email' => 'nullable|boolean',
        ]);

        $targetUser = \App\Models\User::findOrFail($request->user_id);
        $assets     = [];
        $errors     = [];

        foreach ($request->asset_tags as $tag) {
            $tag   = trim($tag);
            $asset = Asset::where('asset_tag', $tag)->first();
            if (!$asset) {
                $errors[] = "Introuvable : {$tag}";
                continue;
            }
            if ($asset->status === 'checked_out') {
                $errors[] = "{$tag} déjà sorti";
                continue;
            }
            $assets[] = $asset;
        }

        if (empty($assets)) {
            return response()->json(['error' => 'Aucun équipement valide. ' . implode('. ', $errors)], 422);
        }

        $names = implode(', ', array_map(fn($a) => $a->name, $assets));
        $title = $request->title ?: "Attribution libre — {$names}";

        $reservation = \App\Models\Reservation::create([
            'user_id'     => $targetUser->id,
            'title'       => $title,
            'start_date'  => now(),
            'end_date'    => $request->end_date,
            'status'      => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'checkout_at' => now(),
        ]);

        foreach ($assets as $asset) {
            $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
            $asset->update([
                'status'                 => 'checked_out',
                'checked_out_to_user_id' => $targetUser->id,
                'expected_return_at'     => $request->end_date,
            ]);
            AssetMovement::create([
                'asset_id'       => $asset->id,
                'reservation_id' => $reservation->id,
                'user_id'        => Auth::id(),
                'action'         => 'checkout',
                'notes'          => "Attribution libre (lot) par " . Auth::user()->name . " → {$targetUser->name}",
            ]);
        }

        $checkoutEmailSent = false;
        if ($request->boolean('send_checkout_email')) {
            try {
                Mail::to($targetUser->email)->send(new CheckoutSummary($reservation->fresh()));
                $checkoutEmailSent = true;
            } catch (\Exception $e) {}
        }

        // Formulaires actifs obligatoires à remplir après attribution
        $pendingForms = CheckoutForm::where('is_active', true)
            ->where('is_required_for_reservation', true)
            ->get(['id', 'name'])
            ->values();

        return response()->json([
            'success'             => true,
            'reservation_id'      => $reservation->id,
            'user'                => $targetUser->name,
            'end_date'            => $reservation->end_date->format('d/m/Y H:i'),
            'assets'              => array_map(fn($a) => ['name' => $a->name, 'asset_tag' => $a->asset_tag], $assets),
            'errors'              => $errors,
            'checkout_email_sent' => $checkoutEmailSent,
            'pending_forms'       => $pendingForms,
        ]);
    }

    /**
     * Attribution libre : scan d'un equipement -> creation automatique d'une reservation active.
     */
    public function freeCheckout(Request $request)
    {
        $request->validate([
            'asset_tag'  => 'required|string',
            'user_id'    => 'required|exists:users,id',
            'end_date'   => 'required|date|after:now',
            'title'      => 'nullable|string|max:255',
        ]);

        $asset = Asset::where('asset_tag', $request->asset_tag)->first();
        if (!$asset) {
            return response()->json(['error' => "Equipement introuvable : {$request->asset_tag}"], 404);
        }

        if ($asset->status === 'checked_out') {
            return response()->json(['error' => "Cet equipement est deja sorti"], 409);
        }

        $targetUser = \App\Models\User::findOrFail($request->user_id);
        $title      = $request->title ?: "Attribution libre — {$asset->name}";

        // Creer une reservation active directement
        $reservation = \App\Models\Reservation::create([
            'user_id'     => $targetUser->id,
            'title'       => $title,
            'start_date'  => now(),
            'end_date'    => $request->end_date,
            'status'      => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'checkout_at' => now(),
        ]);

        $reservation->assets()->attach($asset->id, ['checkout_at' => now()]);
        $asset->update([
            'status'                 => 'checked_out',
            'checked_out_to_user_id' => $targetUser->id,
            'expected_return_at'     => $request->end_date,
        ]);

        AssetMovement::create([
            'asset_id'       => $asset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'checkout',
            'notes'          => "Attribution libre par " . Auth::user()->name . " -> {$targetUser->name}",
        ]);

        // Auto-checkout des accessoires liés
        $asset->load('accessories');
        foreach ($asset->accessories as $accessory) {
            if ($accessory->status === 'checked_out') continue;
            $reservation->assets()->attach($accessory->id, ['checkout_at' => now()]);
            $accessory->update([
                'status'                 => 'checked_out',
                'checked_out_to_user_id' => $targetUser->id,
                'expected_return_at'     => $request->end_date,
            ]);
            AssetMovement::create([
                'asset_id'       => $accessory->id,
                'reservation_id' => $reservation->id,
                'user_id'        => Auth::id(),
                'action'         => 'checkout',
                'notes'          => "Accessoire sorti automatiquement avec {$asset->name}",
            ]);
        }

        return response()->json([
            'success'        => true,
            'asset'          => ['name' => $asset->name, 'asset_tag' => $asset->asset_tag],
            'user'           => $targetUser->name,
            'reservation_id' => $reservation->id,
            'end_date'       => $reservation->end_date->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Substitute original asset with a scanned equivalent of same model,
     * then check it out.
     */
    public function substituteAndCheckout(Request $request)
    {
        $request->validate([
            'reservation_id'    => 'required|exists:reservations,id',
            'original_asset_id' => 'required|exists:assets,id',
            'scanned_asset_id'  => 'required|exists:assets,id',
        ]);

        $reservation   = Reservation::with('assets')->findOrFail($request->reservation_id);
        $originalAsset = Asset::findOrFail($request->original_asset_id);
        $scannedAsset  = Asset::findOrFail($request->scanned_asset_id);

        if ($reservation->status !== 'approved') {
            return response()->json(['error' => "La réservation n'est pas en statut approuvé"], 422);
        }

        // Verify original is in reservation and not yet checked out
        $pivotOriginal = $reservation->assets->find($originalAsset->id);
        if (!$pivotOriginal) {
            return response()->json(['error' => "L'équipement original n'appartient pas à cette réservation"], 422);
        }
        if ($pivotOriginal->pivot->checkout_at) {
            return response()->json(['error' => "L'équipement original a déjà été attribué"], 409);
        }

        // Verify same model
        if ($originalAsset->equipment_model_id !== $scannedAsset->equipment_model_id) {
            return response()->json(['error' => "Les équipements ne sont pas du même modèle"], 422);
        }

        // Remove original from reservation, add scanned asset
        $reservation->assets()->detach($originalAsset->id);
        $reservation->assets()->attach($scannedAsset->id, ['checkout_at' => now()]);

        // Update asset statuses
        $scannedAsset->update([
            'status'                 => 'checked_out',
            'checked_out_to_user_id' => $reservation->user_id,
            'expected_return_at'     => $reservation->end_date,
        ]);

        // Log movement
        AssetMovement::create([
            'asset_id'       => $scannedAsset->id,
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'action'         => 'checkout',
            'notes'          => "Substitution de {$originalAsset->asset_tag} → {$scannedAsset->asset_tag} (même modèle) par " . Auth::user()->name,
        ]);

        // Check if all assets now checked out
        $reservation->refresh();
        $checkoutEmailSent = false;
        if ($reservation->assets->every(fn($a) => $a->pivot->checkout_at !== null)) {
            $reservation->update(['status' => 'active', 'checkout_at' => now()]);
            if ($request->boolean('send_checkout_email')) {
                try {
                    Mail::to($reservation->user->email)->send(new CheckoutSummary($reservation->fresh()));
                    $checkoutEmailSent = true;
                } catch (\Exception $e) {}
            }
        }

        return response()->json([
            'success'             => true,
            'scanned_asset'       => ['id' => $scannedAsset->id, 'name' => $scannedAsset->name, 'asset_tag' => $scannedAsset->asset_tag],
            'original_asset'      => ['id' => $originalAsset->id, 'name' => $originalAsset->name, 'asset_tag' => $originalAsset->asset_tag],
            'checkout_email_sent' => $checkoutEmailSent,
        ]);
    }
}
