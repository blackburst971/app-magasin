<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Computer;
use App\Models\EquipmentModel;
use App\Models\License;
use App\Models\Consommable;
use App\Models\Reservation;
use App\Models\AssetMovement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Historique complet paginé des mouvements (admin).
     */
    public function movements(Request $request)
    {
        $query = AssetMovement::with(['asset', 'user', 'reservation']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('asset', fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('asset_tag', 'like', "%$s%"));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->latest()->paginate(50)->withQueryString()
            ->through(fn($m) => [
                'id'          => $m->id,
                'asset'       => $m->asset?->name ?? 'N/A',
                'asset_tag'   => $m->asset?->asset_tag ?? 'N/A',
                'asset_id'    => $m->asset_id,
                'user'        => $m->user?->name ?? 'Système',
                'action'      => $m->action,
                'action_label'=> $m->action_label,
                'notes'       => $m->notes,
                'reservation_id' => $m->reservation_id,
                'date'        => $m->created_at->format('d/m/Y H:i'),
                'date_raw'    => $m->created_at->toDateTimeString(),
            ]);

        $actions = [
            'checkout'       => 'Retrait',
            'return'         => 'Retour',
            'maintenance'    => 'Maintenance',
            'import'         => 'Import',
            'status_change'  => 'Changement statut',
            'place_assigned' => 'Affectation lieu',
            'place_returned' => 'Retour de lieu',
        ];

        return Inertia::render('Reports/Movements', [
            'movements' => $movements,
            'filters'   => $request->only(['search', 'action', 'from', 'to']),
            'actions'   => $actions,
        ]);
    }

    /**
     * Export des mouvements (PDF ou CSV).
     */
    public function exportMovements(Request $request)
    {
        $query = AssetMovement::with(['asset', 'user']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->whereHas('asset', fn($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('asset_tag', 'like', "%$s%"));
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->latest()->get();
        $format    = $request->input('format', 'csv');
        $filename  = 'mouvements_' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            $rows = '';
            foreach ($movements as $i => $m) {
                $bg = $i % 2 === 0 ? '#fff' : '#f9fafb';
                $rows .= '<tr style="background:' . $bg . '">'
                    . '<td>' . e($m->created_at->format('d/m/Y H:i')) . '</td>'
                    . '<td>' . e($m->asset?->name ?? 'N/A') . '</td>'
                    . '<td style="font-family:monospace">' . e($m->asset?->asset_tag ?? '') . '</td>'
                    . '<td>' . e($m->action_label) . '</td>'
                    . '<td>' . e($m->user?->name ?? 'Système') . '</td>'
                    . '<td>' . e($m->notes ?? '') . '</td>'
                    . '</tr>';
            }
            $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:10px;margin:20px}
h1{font-size:14px;margin:0 0 6px}p{color:#555;font-size:9px;margin:0 0 10px}
table{width:100%;border-collapse:collapse}
th{background:#e5e7eb;text-align:left;padding:5px 7px;border:1px solid #ccc;font-size:9px}
td{padding:4px 7px;border:1px solid #ddd;font-size:9px}
@media print{@page{margin:12mm;size:A4 landscape}}</style></head><body>
<h1>Historique des mouvements</h1>
<p>Exporté le ' . now()->format('d/m/Y H:i') . ' — ' . $movements->count() . ' mouvement(s)</p>
<table><thead><tr><th>Date</th><th>Équipement</th><th>Tag</th><th>Action</th><th>Utilisateur</th><th>Notes</th></tr></thead>
<tbody>' . $rows . '</tbody></table></body></html>';
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
            return $pdf->download($filename . '.pdf');
        }

        // CSV
        return response()->streamDownload(function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Date', 'Équipement', 'Tag', 'Action', 'Utilisateur', 'Notes'], ';');
            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->created_at->format('d/m/Y H:i'),
                    $m->asset?->name ?? 'N/A',
                    $m->asset?->asset_tag ?? '',
                    $m->action_label,
                    $m->user?->name ?? 'Système',
                    $m->notes ?? '',
                ], ';');
            }
            fclose($handle);
        }, $filename . '.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    public function index(Request $request)
    {
        $period = $request->period ?? '30';

        $stats = [
            'total_reservations' => Reservation::where('created_at', '>=', now()->subDays($period))->count(),
            'returned' => Reservation::where('status', 'returned')->where('created_at', '>=', now()->subDays($period))->count(),
            'active' => Reservation::where('status', 'active')->count(),
            'pending' => Reservation::where('status', 'pending')->count(),
        ];

        $topAssets = Asset::withCount(['reservations as total_reservations' => fn($q) =>
                $q->where('reservations.created_at', '>=', now()->subDays($period))
            ])
            ->orderByDesc('total_reservations')
            ->take(10)
            ->get(['id', 'name', 'asset_tag'])
            ->map(fn($a) => ['name' => $a->name, 'tag' => $a->asset_tag, 'count' => $a->total_reservations]);

        $recentMovements = AssetMovement::with(['asset', 'user'])
            ->where('created_at', '>=', now()->subDays($period))
            ->latest()
            ->take(50)
            ->get()
            ->map(fn($m) => [
                'asset' => $m->asset->name ?? 'N/A',
                'asset_tag' => $m->asset->asset_tag ?? 'N/A',
                'user' => $m->user->name ?? 'Système',
                'action' => $m->action_label,
                'notes' => $m->notes,
                'date' => $m->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Reports/Index', compact('stats', 'topAssets', 'recentMovements', 'period'));
    }

    public function export(Request $request)
    {
        $type = $request->type ?? 'movements';
        $period = $request->period ?? 30;

        $data = match($type) {
            'reservations' => Reservation::with(['user', 'assets'])->where('created_at', '>=', now()->subDays($period))->latest()->get(),
            'assets' => Asset::with('category')->orderBy('name')->get(),
            default => AssetMovement::with(['asset', 'user'])->where('created_at', '>=', now()->subDays($period))->latest()->get(),
        };

        $pdf = Pdf::loadView('exports.report', compact('data', 'type'));
        return $pdf->download("rapport_{$type}_" . now()->format('Y-m-d') . '.pdf');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $assets = Asset::with(['category', 'equipmentModel', 'supplier'])
            ->orderBy('name')
            ->get();

        $filename = 'inventaire_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($assets) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header
            fputcsv($handle, [
                'Nom', 'Tag', 'N° Série', 'Catégorie', 'Statut',
                'Emplacement', 'Fabricant', 'Modèle', 'Fournisseur',
                'Date achat', 'Coût (€)', 'Réservable',
            ], ';');

            foreach ($assets as $asset) {
                fputcsv($handle, [
                    $asset->name,
                    $asset->asset_tag,
                    $asset->serial ?? '',
                    $asset->category?->name ?? '',
                    $asset->status_label ?? $asset->status,
                    $asset->location ?? '',
                    $asset->manufacturer ?? '',
                    $asset->equipmentModel?->name ?? $asset->model ?? '',
                    $asset->supplier?->name ?? '',
                    $asset->purchase_date ?? '',
                    $asset->purchase_cost !== null ? number_format($asset->purchase_cost, 2, ',', '') : '',
                    $asset->is_reservable ? 'Oui' : 'Non',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /* ── Export CSV — Licences ──────────────────────────────────────────── */

    public function exportCsvLicenses(): StreamedResponse
    {
        $licenses = License::with(['assignments.computer', 'supplier'])
            ->withCount('assignments')
            ->orderBy('name')
            ->get();

        $filename = 'licences_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($licenses) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Logiciel', 'Version', 'Éditeur', 'Type', 'Cloud',
                'Sièges total', 'Sièges utilisés', 'Clé de licence',
                'Fournisseur', 'Date achat', 'Date expiration',
                'Coût (€)', 'N° commande', 'Notes',
            ], ';');

            $typeLabels = [
                'per_seat'    => 'Par poste',
                'volume'      => 'Volume',
                'oem'         => 'OEM',
                'cloud'       => 'Cloud / SaaS',
                'dongle'      => 'Dongle',
                'open_source' => 'Open Source',
            ];

            foreach ($licenses as $l) {
                fputcsv($handle, [
                    $l->name,
                    $l->version ?? '',
                    $l->manufacturer ?? '',
                    $typeLabels[$l->type] ?? $l->type,
                    $l->is_cloud ? 'Oui' : 'Non',
                    $l->seats !== null ? $l->seats : 'Illimité',
                    $l->assignments_count,
                    $l->license_key ?? '',
                    $l->supplier?->name ?? '',
                    $l->purchase_date?->format('d/m/Y') ?? '',
                    $l->expiry_date?->format('d/m/Y') ?? '',
                    $l->purchase_cost !== null ? number_format($l->purchase_cost, 2, ',', '') : '',
                    $l->order_number ?? '',
                    $l->notes ?? '',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /* ── Export CSV — Postes informatiques ─────────────────────────────── */

    public function exportCsvComputers(): StreamedResponse
    {
        $computers = Computer::withCount('licenseAssignments')
            ->with(['licenseAssignments.license'])
            ->orderBy('name')
            ->get();

        $filename = 'postes_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($computers) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Nom', 'Tag d\'inventaire', 'Type', 'Système d\'exploitation',
                'Version OS', 'N° Série', 'Adresse IP', 'Processeur', 'RAM',
                'Nb licences attribuées', 'Licences', 'Notes',
            ], ';');

            $typeLabels = [
                'desktop'    => 'PC de bureau',
                'laptop'     => 'PC portable',
                'imac'       => 'iMac',
                'macbook'    => 'MacBook',
                'mac_mini'   => 'Mac mini',
                'mac_studio' => 'Mac Studio',
                'server'     => 'Serveur',
                'tablet'     => 'Tablette',
                'other'      => 'Autre',
            ];
            $osLabels = [
                'windows_10'     => 'Windows 10',
                'windows_11'     => 'Windows 11',
                'windows_server' => 'Windows Server',
                'macos'          => 'macOS',
                'linux'          => 'Linux',
                'chromeos'       => 'ChromeOS',
                'other'          => 'Autre',
            ];

            foreach ($computers as $c) {
                $licenseNames = $c->licenseAssignments
                    ->map(fn($a) => $a->license?->name)
                    ->filter()
                    ->implode(' | ');

                fputcsv($handle, [
                    $c->name,
                    $c->asset_tag ?? '',
                    $typeLabels[$c->type] ?? $c->type,
                    $osLabels[$c->os] ?? $c->os,
                    $c->os_version ?? '',
                    $c->serial_number ?? '',
                    $c->ip_address ?? '',
                    $c->processor ?? '',
                    $c->ram ?? '',
                    $c->license_assignments_count,
                    $licenseNames,
                    $c->notes ?? '',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /* ── Export CSV — Consommables ──────────────────────────────────────── */

    public function exportCsvConsommables(): StreamedResponse
    {
        $consommables = Consommable::with('category')
            ->orderBy('name')
            ->get();

        $filename = 'consommables_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($consommables) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Nom', 'Référence', 'Catégorie', 'Unité',
                'Quantité', 'Stock minimum', 'Alerte stock',
                'Coût unitaire (€)', 'Valeur stock (€)',
                'Commandable', 'Description',
            ], ';');

            foreach ($consommables as $c) {
                $stockAlert = $c->quantity <= 0
                    ? 'Rupture'
                    : ($c->is_low_stock ? 'Stock bas' : 'OK');

                fputcsv($handle, [
                    $c->name,
                    $c->reference ?? '',
                    $c->category?->name ?? '',
                    $c->unit ?? '',
                    $c->quantity,
                    $c->min_stock ?? '',
                    $stockAlert,
                    $c->unit_cost !== null ? number_format($c->unit_cost, 2, ',', '') : '',
                    number_format($c->stock_value, 2, ',', ''),
                    $c->is_requestable ? 'Oui' : 'Non',
                    $c->description ?? '',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /* ── Export CSV — Modèles d'équipements ─────────────────────────────── */

    public function exportCsvModels(): StreamedResponse
    {
        $models = EquipmentModel::with(['category', 'manufacturerModel', 'supplier'])
            ->withCount('assets')
            ->orderBy('name')
            ->get();

        $filename = 'modeles_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($models) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Modèle', 'Fabricant', 'Catégorie', 'Fournisseur',
                'Nb équipements', 'Description',
            ], ';');

            foreach ($models as $m) {
                fputcsv($handle, [
                    $m->name,
                    $m->manufacturerModel?->name ?? $m->manufacturer ?? '',
                    $m->category?->name ?? '',
                    $m->supplier?->name ?? '',
                    $m->assets_count,
                    $m->description ?? '',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
