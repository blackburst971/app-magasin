<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetReport;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\AssetReportNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssetReportController extends Controller
{
    /**
     * Utilisateur soumet un rapport après retour d'une réservation.
     */
    public function store(Request $request, Reservation $reservation)
    {
        // Le rapport ne peut être soumis que par le propriétaire ou un admin
        if ($reservation->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            abort(403);
        }

        $data = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'type'     => 'required|in:damage,loss,malfunction,observation',
            'title'    => 'required|string|max:255',
            'content'  => 'required|string|max:5000',
        ]);

        // Vérifier que l'asset appartient bien à cette réservation
        $assetInReservation = $reservation->assets()->where('assets.id', $data['asset_id'])->exists();
        if (!$assetInReservation) abort(422, 'Cet équipement n\'appartient pas à cette réservation.');

        $report = AssetReport::create([
            'asset_id'       => $data['asset_id'],
            'reservation_id' => $reservation->id,
            'user_id'        => Auth::id(),
            'type'           => $data['type'],
            'title'          => $data['title'],
            'content'        => $data['content'],
            'is_validated'   => false,
        ]);

        $report->load('asset', 'user');

        // Notifier tous les admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AssetReportNotification($report));
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Rapport envoyé. L\'administrateur en sera informé.',
            'report'  => $this->formatReport($report),
        ]);
    }

    /**
     * Admin valide un rapport (le rend visible sur la fiche).
     */
    public function validate(Request $request, AssetReport $report)
    {
        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $report->update([
            'is_validated' => true,
            'validated_at' => now(),
            'validated_by' => Auth::id(),
            'admin_notes'  => $data['admin_notes'] ?? $report->admin_notes,
        ]);

        return response()->json(['ok' => true, 'report' => $this->formatReport($report->fresh(['user', 'validatedBy', 'reservation']))]);
    }

    /**
     * Admin met à jour un rapport (notes, contenu, type…).
     */
    public function update(Request $request, AssetReport $report)
    {
        $data = $request->validate([
            'title'       => 'nullable|string|max:255',
            'content'     => 'nullable|string|max:5000',
            'type'        => 'nullable|in:damage,loss,malfunction,observation',
            'admin_notes' => 'nullable|string|max:2000',
            'is_validated'=> 'nullable|boolean',
        ]);

        $report->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['ok' => true, 'report' => $this->formatReport($report->fresh(['user', 'validatedBy', 'reservation']))]);
    }

    /**
     * Admin supprime un rapport.
     */
    public function destroy(AssetReport $report)
    {
        $report->delete();
        return response()->json(['ok' => true]);
    }

    // ── Helper ──────────────────────────────────────────────────────────────────

    private function formatReport(AssetReport $r): array
    {
        return [
            'id'           => $r->id,
            'type'         => $r->type,
            'type_label'   => $r->getTypeLabel(),
            'title'        => $r->title,
            'content'      => $r->content,
            'is_validated' => $r->is_validated,
            'admin_notes'  => $r->admin_notes,
            'validated_at' => $r->validated_at?->format('d/m/Y H:i'),
            'validated_by' => $r->validatedBy ? ['name' => $r->validatedBy->name] : null,
            'created_at'   => $r->created_at->format('d/m/Y H:i'),
            'user'         => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            'reservation_id' => $r->reservation_id,
        ];
    }
}
