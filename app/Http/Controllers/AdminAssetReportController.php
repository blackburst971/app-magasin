<?php

namespace App\Http\Controllers;

use App\Models\AdminAssetReport;
use App\Models\Asset;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AdminAssetReportController extends Controller
{
    /**
     * Afficher le formulaire de création d'un rapport admin pour un asset.
     */
    public function create(Request $request, Asset $asset)
    {
        $reservationId = $request->query('reservation_id');
        $returnCondition = $request->query('return_condition');

        return Inertia::render('AdminReports/Create', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'return_check_note' => $asset->return_check_note,
            ],
            'reservationId' => $reservationId,
            'returnCondition' => $returnCondition,
        ]);
    }

    /**
     * Enregistrer un nouveau rapport admin.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'reservation_id' => 'nullable|exists:reservations,id',
            'type' => 'required|in:damage,loss,malfunction,observation,return_issue',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'return_condition' => 'nullable|in:good,damaged,lost,pending',
        ]);

        $data['created_by'] = Auth::id();

        $report = AdminAssetReport::create($data);

        // Si le rapport concerne un retour endommagé ou perdu
        if (in_array($data['return_condition'], ['damaged', 'lost'])) {
            $asset = Asset::find($data['asset_id']);
            if ($asset) {
                // Déterminer le nouveau statut
                $newStatus = $data['return_condition'] === 'lost' ? 'perdu' : 'damaged';

                // Trouver la réservation active de cet équipement
                $reservation = null;
                if ($data['reservation_id']) {
                    $reservation = Reservation::find($data['reservation_id']);
                } else {
                    // Chercher une réservation active avec cet équipement
                    $reservation = Reservation::whereHas('assets', fn($q) => $q->where('assets.id', $asset->id))
                        ->whereIn('status', ['active', 'approved'])
                        ->first();
                }

                // Si l'équipement est dans une réservation active, marquer le retour et le détacher
                if ($reservation) {
                    // Marquer comme retourné dans le pivot
                    $reservation->assets()->updateExistingPivot($asset->id, [
                        'returned_at' => now(),
                    ]);

                    // Créer un mouvement pour tracer le retour
                    \App\Models\AssetMovement::create([
                        'asset_id' => $asset->id,
                        'reservation_id' => $reservation->id,
                        'user_id' => Auth::id(),
                        'action' => 'return',
                        'notes' => "Retourné avec état: {$data['return_condition']} - Rapport #{$report->id}",
                    ]);

                    // Vérifier si tous les équipements sont retournés
                    $allReturned = $reservation->assets()
                        ->wherePivotNull('returned_at')
                        ->doesntExist();

                    if ($allReturned) {
                        $reservation->update(['status' => 'returned']);
                    }
                }

                // Mettre à jour le statut de l'équipement
                $asset->update([
                    'status' => $newStatus,
                    'checked_out_to_user_id' => null,
                    'expected_return_at' => null,
                ]);
            }
        }

        return redirect()->route('assets.show', $data['asset_id'])
            ->with('success', 'Rapport de signalement enregistré avec succès.');
    }

    /**
     * Afficher le formulaire d'édition d'un rapport admin.
     */
    public function edit(AdminAssetReport $report)
    {
        $report->load(['asset', 'reservation', 'creator']);

        return Inertia::render('AdminReports/Edit', [
            'report' => [
                'id' => $report->id,
                'asset_id' => $report->asset_id,
                'reservation_id' => $report->reservation_id,
                'type' => $report->type,
                'title' => $report->title,
                'content' => $report->content,
                'return_condition' => $report->return_condition,
                'created_at' => $report->created_at->format('d/m/Y H:i'),
                'asset' => [
                    'id' => $report->asset->id,
                    'name' => $report->asset->name,
                    'asset_tag' => $report->asset->asset_tag,
                ],
            ],
        ]);
    }

    /**
     * Mettre à jour un rapport admin existant.
     */
    public function update(Request $request, AdminAssetReport $report)
    {
        $data = $request->validate([
            'type' => 'required|in:damage,loss,malfunction,observation,return_issue',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'return_condition' => 'nullable|in:good,damaged,lost,pending',
        ]);

        $report->update($data);

        return redirect()->route('assets.show', $report->asset_id)
            ->with('success', 'Rapport de signalement mis à jour.');
    }

    /**
     * Supprimer un rapport admin.
     */
    public function destroy(AdminAssetReport $report)
    {
        $assetId = $report->asset_id;
        $report->delete();

        return redirect()->route('assets.show', $assetId)
            ->with('success', 'Rapport supprimé.');
    }
}
