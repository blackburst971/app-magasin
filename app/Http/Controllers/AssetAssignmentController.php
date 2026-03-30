<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetAssignmentController extends Controller
{
    /**
     * Affecter un équipement à un autre équipement (admin only).
     */
    public function assign(Request $request)
    {
        $request->validate([
            'asset_id'             => 'required|exists:assets,id',
            'assigned_to_asset_id' => 'required|exists:assets,id|different:asset_id',
            'notes'                => 'nullable|string|max:500',
        ]);

        $asset = Asset::findOrFail($request->asset_id);

        // Vérifier qu'il n'est pas déjà affecté à un autre équipement
        if ($asset->activeAssignment()->exists()) {
            return back()->with('error', 'Cet équipement est déjà affecté à un autre équipement.');
        }

        // Vérifier qu'il n'est pas en cours d'utilisation (sorti)
        if ($asset->status === 'checked_out') {
            return back()->with('error', 'Cet équipement est actuellement sorti, impossible de l\'affecter.');
        }

        AssetAssignment::create([
            'asset_id'             => $asset->id,
            'assigned_to_asset_id' => $request->assigned_to_asset_id,
            'assigned_by_user_id'  => Auth::id(),
            'notes'                => $request->notes,
            'assigned_at'          => now(),
        ]);

        // Marquer l'asset comme indisponible
        $asset->update(['status' => 'assigned_to_asset']);

        return back()->with('success', "Équipement affecté avec succès.");
    }

    /**
     * Retourner un équipement affecté (admin only).
     * Se fait depuis la page de l'asset destinataire.
     */
    public function returnAsset(Request $request, AssetAssignment $assignment)
    {
        if ($assignment->returned_at !== null) {
            return back()->with('error', 'Cet équipement a déjà été retourné.');
        }

        $assignment->update(['returned_at' => now()]);
        $assignment->asset->update(['status' => 'ready_to_deploy']);

        return back()->with('success', "Équipement {$assignment->asset->name} retourné avec succès.");
    }

    /**
     * Retourner tous les équipements affectés à un asset (admin only).
     */
    public function returnAll(Request $request, Asset $asset)
    {
        $assignments = AssetAssignment::where('assigned_to_asset_id', $asset->id)
            ->whereNull('returned_at')
            ->with('asset')
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->update(['returned_at' => now()]);
            $assignment->asset->update(['status' => 'ready_to_deploy']);
        }

        return back()->with('success', "{$assignments->count()} équipement(s) retourné(s).");
    }
}
