<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\EquipmentModel;
use App\Models\InventoryReport;
use App\Models\InventoryReportItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    /**
     * Page de sélection des modèles à inventorier.
     */
    public function index()
    {
        $models = EquipmentModel::withCount('assets')
            ->orderBy('name')
            ->get(['id', 'name', 'image'])
            ->map(fn($m) => [
                'id'           => $m->id,
                'name'         => $m->name,
                'image_url'    => $m->image ? Storage::disk('public')->url($m->image) : null,
                'assets_count' => $m->assets_count,
            ]);

        return Inertia::render('Inventory/Index', [
            'models' => $models,
        ]);
    }

    /**
     * Page de session d'inventaire pour les modèles sélectionnés.
     */
    public function session(Request $request)
    {
        $request->validate([
            'models'   => 'required|array|min:1',
            'models.*' => 'integer|exists:equipment_models,id',
        ]);

        $modelIds = $request->models;

        // Récupérer tous les équipements (disponibles et non-disponibles)
        $allAssets = Asset::whereIn('equipment_model_id', $modelIds)
            ->with('equipmentModel')
            ->orderBy('name')
            ->get();

        // Séparer les équipements disponibles (scannables) des autres (informatifs)
        $availableAssets = $allAssets->where('status', 'available')->values()->map(fn($a) => [
            'id'         => $a->id,
            'name'       => $a->name,
            'asset_tag'  => $a->asset_tag,
            'serial'     => $a->serial,
            'status'     => $a->status,
            'location'   => $a->location,
            'model_name' => $a->equipmentModel?->name,
            'is_scannable' => true,
            'image'      => $a->image
                ? Storage::disk('public')->url($a->image)
                : ($a->equipmentModel?->image
                    ? Storage::disk('public')->url($a->equipmentModel->image)
                    : null),
        ]);

        $unavailableAssets = $allAssets->where('status', '!=', 'available')->values()->map(fn($a) => [
            'id'         => $a->id,
            'name'       => $a->name,
            'asset_tag'  => $a->asset_tag,
            'serial'     => $a->serial,
            'status'     => $a->status,
            'location'   => $a->location,
            'model_name' => $a->equipmentModel?->name,
            'is_scannable' => false,
            'image'      => $a->image
                ? Storage::disk('public')->url($a->image)
                : ($a->equipmentModel?->image
                    ? Storage::disk('public')->url($a->equipmentModel->image)
                    : null),
        ]);

        $models = EquipmentModel::whereIn('id', $modelIds)->get(['id', 'name']);

        return Inertia::render('Inventory/Session', [
            'assets'              => $availableAssets,
            'unavailableAssets'   => $unavailableAssets,
            'modelNames'          => $models->pluck('name')->toArray(),
            'modelIds'            => $modelIds,
        ]);
    }

    /**
     * Sauvegarder la session d'inventaire comme rapport.
     */
    public function saveReport(Request $request)
    {
        $data = $request->validate([
            'label'       => 'nullable|string|max:255',
            'model_names' => 'required|array',
            'model_names.*' => 'string',
            'notes'       => 'nullable|string|max:2000',
            'items'       => 'required|array',
            'items.*.asset_id'    => 'nullable|integer',
            'items.*.asset_tag'   => 'required|string',
            'items.*.asset_name'  => 'required|string',
            'items.*.model_name'  => 'nullable|string',
            'items.*.location'    => 'nullable|string',
            'items.*.serial'      => 'nullable|string',
            'items.*.scan_status' => 'required|in:present,missing,pending',
        ]);

        $presentCount = collect($data['items'])->where('scan_status', 'present')->count();
        $missingCount = collect($data['items'])->where('scan_status', 'missing')->count();
        $pendingCount = collect($data['items'])->where('scan_status', 'pending')->count();

        // Sauvegarder les statuts précédents des équipements pour permettre l'annulation
        $previousStatuses = [];
        foreach ($data['items'] as $item) {
            if ($item['asset_id']) {
                $asset = Asset::find($item['asset_id']);
                if ($asset) {
                    $previousStatuses[$item['asset_id']] = $asset->status;
                }
            }
        }

        $report = InventoryReport::create([
            'session_date'       => now()->toDateString(),
            'label'              => $data['label'] ?? null,
            'model_names'        => $data['model_names'],
            'total_count'        => count($data['items']),
            'present_count'      => $presentCount,
            'missing_count'      => $missingCount,
            'pending_count'      => $pendingCount,
            'notes'              => $data['notes'] ?? null,
            'created_by'         => Auth::id(),
            'previous_statuses'  => $previousStatuses,
            'is_draft'           => false, // Rapport finalisé
        ]);

        // Changer le statut des équipements absents en "en_attente"
        foreach ($data['items'] as $item) {
            if ($item['asset_id'] && $item['scan_status'] === 'missing') {
                $asset = Asset::find($item['asset_id']);
                if ($asset && $asset->status === 'available') {
                    $asset->update(['status' => 'en_attente']);
                }
            }

            // Créer l'item du rapport
            InventoryReportItem::create([
                'inventory_report_id' => $report->id,
                'asset_id'   => $item['asset_id'] ?? null,
                'asset_tag'  => $item['asset_tag'],
                'asset_name' => $item['asset_name'],
                'model_name' => $item['model_name'] ?? null,
                'location'   => $item['location'] ?? null,
                'serial'     => $item['serial'] ?? null,
                'scan_status'=> $item['scan_status'],
            ]);
        }

        return redirect()->route('inventory.history')
            ->with('success', 'Rapport d\'inventaire sauvegardé. ' . $missingCount . ' équipement(s) absent(s) passé(s) en attente.');
    }

    /**
     * Historique des rapports d'inventaire.
     */
    public function history(Request $request)
    {
        $query = InventoryReport::with('creator')
            ->withCount('items');

        // Filtre archivé / non archivé
        $showArchived = $request->boolean('archived', false);
        $query->where('is_archived', $showArchived);

        // Filtre par mois / année
        if ($request->filled('month')) {
            $query->whereYear('session_date', substr($request->month, 0, 4))
                  ->whereMonth('session_date', substr($request->month, 5, 2));
        }

        $reports = $query->latest('session_date')->latest('id')->paginate(20)->withQueryString()
            ->through(fn($r) => [
                'id'            => $r->id,
                'session_date'  => $r->session_date->format('d/m/Y'),
                'session_date_raw' => $r->session_date->toDateString(),
                'label'         => $r->label,
                'model_names'   => $r->model_names,
                'total_count'   => $r->total_count,
                'present_count' => $r->present_count,
                'missing_count' => $r->missing_count,
                'pending_count' => $r->pending_count,
                'progress_percent' => $r->progress_percent_attribute,
                'is_archived'   => $r->is_archived,
                'creator'       => $r->creator?->name ?? 'Système',
                'created_at'    => $r->created_at->format('d/m/Y H:i'),
                'notes'         => $r->notes,
            ]);

        // Mois disponibles pour le filtre
        $availableMonths = InventoryReport::selectRaw("strftime('%Y-%m', session_date) as month")
            ->groupBy('month')
            ->orderByDesc('month')
            ->pluck('month');

        return Inertia::render('Inventory/History', [
            'reports'        => $reports,
            'filters'        => $request->only(['archived', 'month']),
            'availableMonths'=> $availableMonths,
        ]);
    }

    /**
     * Afficher un rapport d'inventaire.
     */
    public function showReport(InventoryReport $report)
    {
        $report->load(['creator', 'items']);

        return Inertia::render('Inventory/ShowReport', [
            'report' => [
                'id'            => $report->id,
                'session_date'  => $report->session_date->format('d/m/Y'),
                'label'         => $report->label,
                'model_names'   => $report->model_names,
                'total_count'   => $report->total_count,
                'present_count' => $report->present_count,
                'missing_count' => $report->missing_count,
                'pending_count' => $report->pending_count,
                'is_archived'   => $report->is_archived,
                'creator'       => $report->creator?->name ?? 'Système',
                'created_at'    => $report->created_at->format('d/m/Y H:i'),
                'notes'         => $report->notes,
                'items'         => $report->items->map(fn($item) => [
                    'id'          => $item->id,
                    'asset_tag'   => $item->asset_tag,
                    'asset_name'  => $item->asset_name,
                    'model_name'  => $item->model_name,
                    'location'    => $item->location,
                    'serial'      => $item->serial,
                    'scan_status' => $item->scan_status,
                ])->values(),
            ],
        ]);
    }

    /**
     * Mettre à jour le label / notes d'un rapport.
     */
    public function updateReport(Request $request, InventoryReport $report)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $report->update($data);
        return back()->with('success', 'Rapport mis à jour.');
    }

    /**
     * Archiver / désarchiver un rapport.
     */
    public function archiveReport(InventoryReport $report)
    {
        $report->update(['is_archived' => !$report->is_archived]);
        $msg = $report->is_archived ? 'Rapport archivé.' : 'Rapport désarchivé.';
        return back()->with('success', $msg);
    }

    /**
     * Supprimer un rapport.
     */
    public function destroyReport(InventoryReport $report)
    {
        $report->delete();
        return redirect()->route('inventory.history')->with('success', 'Rapport supprimé.');
    }

    /**
     * Annuler un inventaire et restaurer les statuts précédents.
     * L'inventaire ne doit pas figurer dans l'historique après annulation.
     */
    public function cancelReport(InventoryReport $report)
    {
        // Restaurer les statuts précédents des équipements
        if ($report->previous_statuses) {
            foreach ($report->previous_statuses as $assetId => $previousStatus) {
                $asset = Asset::find($assetId);
                if ($asset) {
                    // Restaurer uniquement si le statut actuel est "en_attente" (pour éviter de modifier un statut qui a changé entre temps)
                    if ($asset->status === 'en_attente') {
                        $asset->update(['status' => $previousStatus]);
                    }
                }
            }
        }

        // Supprimer le rapport et ses items (ne pas l'archiver, le supprimer définitivement)
        $report->delete();

        return redirect()->route('inventory.history')
            ->with('success', 'Inventaire annulé. Les statuts des équipements ont été restaurés.');
    }

    /**
     * Exporter un rapport d'inventaire en PDF ou CSV.
     */
    public function exportReport(Request $request, InventoryReport $report)
    {
        $report->load('items');
        $format = $request->input('format', 'pdf');
        $filename = 'inventaire_' . $report->session_date->format('Y-m-d') . '_' . $report->id;

        if ($format === 'csv') {
            return $this->exportReportCsv($report, $filename);
        }

        return $this->exportReportPdf($report, $filename);
    }

    private function exportReportPdf(InventoryReport $report, string $filename)
    {
        $html = $this->buildReportHtml($report);
        $pdf  = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
        return $pdf->download($filename . '.pdf');
    }

    private function exportReportCsv(InventoryReport $report, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Rapport d\'inventaire',
                $report->label ?? ('Inventaire du ' . $report->session_date->format('d/m/Y')),
            ], ';');
            fputcsv($handle, ['Modèles', implode(', ', $report->model_names)], ';');
            fputcsv($handle, ['Date', $report->session_date->format('d/m/Y')], ';');
            fputcsv($handle, ['Réalisé par', $report->creator?->name ?? 'N/A'], ';');
            fputcsv($handle, ['Total', $report->total_count, 'Présents', $report->present_count, 'Manquants', $report->missing_count, 'En attente', $report->pending_count], ';');
            fputcsv($handle, [], ';');
            fputcsv($handle, ['Tag', 'Nom', 'Modèle', 'N° Série', 'Emplacement', 'Statut'], ';');

            $statusLabel = ['present' => 'Présent', 'missing' => 'Manquant', 'pending' => 'Non traité'];
            foreach ($report->items->sortBy('asset_name') as $item) {
                fputcsv($handle, [
                    $item->asset_tag,
                    $item->asset_name,
                    $item->model_name ?? '',
                    $item->serial ?? '',
                    $item->location ?? '',
                    $statusLabel[$item->scan_status] ?? $item->scan_status,
                ], ';');
            }
            fclose($handle);
        }, $filename . '.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function buildReportHtml(InventoryReport $report): string
    {
        $title    = $report->label ?? ('Inventaire du ' . $report->session_date->format('d/m/Y'));
        $models   = implode(', ', $report->model_names);
        $now      = now()->format('d/m/Y H:i');
        $creator  = $report->creator?->name ?? 'N/A';
        $notes    = $report->notes ? '<p class="notes"><strong>Notes :</strong> ' . e($report->notes) . '</p>' : '';

        $statusStyle = [
            'present' => 'color:#16a34a;font-weight:600',
            'missing' => 'color:#dc2626;font-weight:600',
            'pending' => 'color:#9ca3af',
        ];
        $statusLabel = ['present' => '✓ Présent', 'missing' => '✗ Manquant', 'pending' => '— En attente'];

        $rows = '';
        foreach ($report->items->sortBy('asset_name') as $i => $item) {
            $bg   = $i % 2 === 0 ? '#fff' : '#f9fafb';
            $rows .= '<tr style="background:' . $bg . '">'
                . '<td>' . e($item->asset_name) . '</td>'
                . '<td style="font-family:monospace">' . e($item->asset_tag) . '</td>'
                . '<td style="font-family:monospace">' . e($item->serial ?? '—') . '</td>'
                . '<td>' . e($item->model_name ?? '—') . '</td>'
                . '<td>' . e($item->location ?? '—') . '</td>'
                . '<td style="text-align:center;' . ($statusStyle[$item->scan_status] ?? '') . '">'
                    . ($statusLabel[$item->scan_status] ?? $item->scan_status)
                . '</td>'
                . '</tr>';
        }

        return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:11px;margin:20px;color:#111}
h1{font-size:15px;margin:0 0 4px}
.meta{color:#555;font-size:10px;margin:2px 0}.stats{display:flex;gap:16px;margin:8px 0;font-size:11px}
.stat{background:#f3f4f6;padding:4px 10px;border-radius:4px;text-align:center}
.stat strong{display:block;font-size:16px}
.notes{margin:6px 0;font-size:10px;color:#555;background:#fffbeb;border-left:3px solid #f59e0b;padding:4px 8px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th{background:#e5e7eb;text-align:left;padding:5px 7px;border:1px solid #ccc;font-size:10px;white-space:nowrap}
td{padding:4px 7px;border:1px solid #ddd;vertical-align:middle;font-size:10px}
@media print{@page{margin:12mm}}
</style></head><body>
<h1>' . e($title) . '</h1>
<p class="meta">Modèles : ' . e($models) . ' &nbsp;·&nbsp; Date : ' . e($report->session_date->format('d/m/Y')) . ' &nbsp;·&nbsp; Par : ' . e($creator) . ' &nbsp;·&nbsp; Imprimé le ' . $now . '</p>
<div class="stats">
  <div class="stat"><strong>' . $report->total_count . '</strong>Total</div>
  <div class="stat" style="color:#16a34a"><strong>' . $report->present_count . '</strong>Présents</div>
  <div class="stat" style="color:#dc2626"><strong>' . $report->missing_count . '</strong>Manquants</div>
  <div class="stat" style="color:#9ca3af"><strong>' . $report->pending_count . '</strong>Non traités</div>
</div>
' . $notes . '
<table>
<thead><tr><th>Nom</th><th>Tag</th><th>N° Série</th><th>Modèle</th><th>Emplacement</th><th>Statut</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>
</body></html>';
    }
}
