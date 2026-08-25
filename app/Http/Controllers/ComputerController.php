<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Computer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use League\Csv\Writer;

class ComputerController extends Controller
{
    /* ── Contrôle d'accès non-admin ──────────────────────────────────────── */

    private function checkSectionAccess(string $section): void
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) return;

        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        if (Setting::get("section_{$section}_{$role}", 'false') !== 'true') {
            abort(403, 'Accès non autorisé.');
        }
    }

    /* ── Parsage des adresses IP envoyées en JSON string ─────────────────── */

    private function parseIpAddresses(Request $request): array
    {
        $raw = $request->input('ip_addresses', '[]');
        if (is_array($raw)) {
            $parsed = $raw;
        } else {
            $parsed = json_decode($raw, true) ?? [];
        }
        // Filtrer les entrées vides et normaliser
        return array_values(array_filter($parsed, fn($e) => !empty(trim($e['ip'] ?? ''))));
    }

    /* ── Index ──────────────────────────────────────────────────────────── */

    public function index()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        $this->checkSectionAccess('computers');

        $computers = Computer::withCount('licenseAssignments')
            ->with(['licenseAssignments.license', 'asset'])
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($isAdmin) {
                return [
                    'id'                        => $c->id,
                    'asset_id'                  => $c->asset_id,
                    'asset_tag_asset'           => $c->asset?->asset_tag,
                    'asset_is_reservable'       => $c->asset ? (bool) $c->asset->is_reservable : null,
                    'name'                      => $c->name,
                    'asset_tag'                 => $c->asset_tag,
                    'type'                      => $c->type,
                    'os'                        => $c->os,
                    'os_version'                => $c->os_version,
                    'serial_number'             => $isAdmin ? $c->serial_number : null,
                    'ip_addresses'              => $c->ip_addresses ?? [],
                    'processor'                 => $c->processor,
                    'ram'                       => $c->ram,
                    'notes'                     => $isAdmin ? $c->notes : null,
                    'photo_url'                 => $c->photo ? Storage::url($c->photo) : null,
                    'license_assignments_count' => $c->license_assignments_count,
                    'licenses'                  => $c->licenseAssignments->map(fn($a) => [
                        'id'          => $a->id,
                        'license_id'  => $a->license_id,
                        'name'        => $a->license->name,
                        'version'     => $a->license->version,
                        'type'        => $a->license->type,
                        'is_cloud'    => $a->license->is_cloud,
                        'expiry_date' => $a->license->expiry_date?->format('Y-m-d'),
                        'is_expired'  => $a->license->is_expired,
                        'notes'       => $isAdmin ? $a->notes : null,
                        'assigned_at' => $isAdmin ? $a->assigned_at?->format('Y-m-d') : null,
                    ]),
                ];
            });

        $availableAssets = $isAdmin
            ? Asset::whereNotIn('id', Computer::whereNotNull('asset_id')->pluck('asset_id')->toArray())
                ->orderBy('name')
                ->get()
                ->map(fn($a) => [
                    'id'            => $a->id,
                    'name'          => $a->name,
                    'asset_tag'     => $a->asset_tag,
                    'serial'        => $a->serial ?? null,
                    'is_reservable' => (bool) $a->is_reservable,
                    'status'        => $a->status,
                ])
            : [];

        $canEdit      = false;
        $editableFields = [];
        if (!$isAdmin) {
            $user = auth()->user();
            $role = $user->hasRole('professeur') ? 'professeur'
                  : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');
            $canEdit = Setting::get("section_computers_edit_{$role}", 'false') === 'true';
            if ($canEdit) {
                foreach (['ip_addresses', 'ram', 'processor', 'os_version', 'os', 'notes'] as $field) {
                    if (Setting::get("section_computers_edit_field_{$field}_{$role}", 'false') === 'true') {
                        $editableFields[] = $field;
                    }
                }
            }
        }

        return Inertia::render('Computers/Index', [
            'computers'      => $computers,
            'availableAssets' => $availableAssets,
            'isAdmin'        => $isAdmin,
            'canEdit'        => $canEdit,
            'editableFields' => $editableFields,
        ]);
    }

    /* ── Adressage IP ───────────────────────────────────────────────────── */

    public function ipAddressing()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        $this->checkSectionAccess('computers_ip');

        $computers = Computer::with(['licenseAssignments.license'])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'name'         => $c->name,
                'asset_tag'    => $c->asset_tag,
                'type'         => $c->type,
                'os'           => $c->os,
                'os_version'   => $c->os_version,
                'ip_addresses' => $c->ip_addresses ?? [],
                'processor'    => $c->processor,
                'ram'          => $c->ram,
                'photo_url'    => $c->photo ? Storage::url($c->photo) : null,
                'licenses'     => $c->licenseAssignments->map(fn($a) => [
                    'name'       => $a->license->name,
                    'version'    => $a->license->version,
                    'type'       => $a->license->type,
                    'is_expired' => $a->license->is_expired,
                ])->values(),
            ]);

        return Inertia::render('Computers/IpAddressing', [
            'computers' => $computers,
            'isAdmin'   => $isAdmin,
        ]);
    }

    /* ── Export IP PDF ──────────────────────────────────────────────────── */

    public function exportIpPdf(Request $request)
    {
        $computers = Computer::with(['licenseAssignments.license'])
            ->orderBy('name')
            ->get()
            ->map(fn($c) => [
                'name'         => $c->name,
                'asset_tag'    => $c->asset_tag,
                'type'         => $c->getTypeLabel(),
                'os'           => $c->getOsLabel(),
                'os_version'   => $c->os_version,
                'ip_addresses' => $c->ip_addresses ?? [],
                'processor'    => $c->processor,
                'ram'          => $c->ram,
                'licenses'     => $c->licenseAssignments->map(fn($a) => $a->license->name . ($a->license->version ? ' v'.$a->license->version : ''))->implode(', '),
            ]);

        $pdf = Pdf::loadView('pdf.ip-addressing-export', compact('computers'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('adressage-ip-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ── Export IP CSV ──────────────────────────────────────────────────── */

    public function exportIpCsv(Request $request)
    {
        $computers = Computer::with(['licenseAssignments.license'])
            ->orderBy('name')
            ->get();

        $csv = Writer::createFromString();
        $csv->setDelimiter(';');
        $csv->insertOne(["\xEF\xBB\xBF"]); // BOM UTF-8

        $csv->insertOne(['Nom du poste', 'Tag', 'Type', 'OS', 'Version OS', 'Adresse IP', 'Description carte réseau', 'Processeur', 'RAM', 'Licences']);

        foreach ($computers as $c) {
            $licenses    = $c->licenseAssignments->map(fn($a) => $a->license->name . ($a->license->version ? ' v'.$a->license->version : ''))->implode(', ');
            $ipAddresses = $c->ip_addresses ?? [];

            if (empty($ipAddresses)) {
                $csv->insertOne([$c->name, $c->asset_tag ?? '', $c->getTypeLabel(), $c->getOsLabel(), $c->os_version ?? '', '', '', $c->processor ?? '', $c->ram ?? '', $licenses]);
            } else {
                foreach ($ipAddresses as $i => $entry) {
                    $csv->insertOne([
                        $i === 0 ? $c->name       : '',
                        $i === 0 ? ($c->asset_tag ?? '') : '',
                        $i === 0 ? $c->getTypeLabel() : '',
                        $i === 0 ? $c->getOsLabel()   : '',
                        $i === 0 ? ($c->os_version ?? '') : '',
                        $entry['ip']          ?? '',
                        $entry['description'] ?? '',
                        $i === 0 ? ($c->processor ?? '') : '',
                        $i === 0 ? ($c->ram ?? '')       : '',
                        $i === 0 ? $licenses             : '',
                    ]);
                }
            }
        }

        return response((string) $csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="adressage-ip-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    /* ── Bulk Update (modification en masse — admin) ───────────────────── */

    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'ids'        => 'required|array|min:1',
            'ids.*'      => 'required|integer|exists:computers,id',
            'fields'     => 'required|array|min:1',
            'fields.*'   => 'in:type,os,os_version,processor,ram,notes',
            'type'       => 'nullable|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'         => 'nullable|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version' => 'nullable|string|max:100',
            'processor'  => 'nullable|string|max:255',
            'ram'        => 'nullable|string|max:50',
            'notes'      => 'nullable|string',
        ]);

        $update = [];
        foreach ($data['fields'] as $field) {
            $update[$field] = $data[$field] ?? null;
        }

        Computer::whereIn('id', $data['ids'])->update($update);

        $count = count($data['ids']);
        return back()->with('success', "{$count} poste(s) mis à jour avec succès.");
    }

    /* ── Bulk Delete (suppression en masse — admin) ─────────────────────── */

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:computers,id',
        ]);

        $computers = Computer::whereIn('id', $data['ids'])->get();
        foreach ($computers as $c) {
            if ($c->photo) {
                Storage::disk('public')->delete($c->photo);
            }
            $c->delete();
        }

        $count = count($data['ids']);
        return back()->with('success', "{$count} poste(s) supprimé(s) avec succès.");
    }

    /* ── Batch Store (depuis l'inventaire — plusieurs postes à la fois) ─── */

    public function batchStore(Request $request)
    {
        $data = $request->validate([
            'asset_ids'     => 'required|array|min:1',
            'asset_ids.*'   => 'required|integer|exists:assets,id',
            'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version'    => 'nullable|string|max:100',
            'processor'     => 'nullable|string|max:255',
            'ram'           => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
        ]);

        $created = 0;
        $skipped = 0;

        foreach ($data['asset_ids'] as $assetId) {
            // Ignorer si déjà lié à un poste
            if (Computer::where('asset_id', $assetId)->exists()) {
                $skipped++;
                continue;
            }

            $asset = Asset::find($assetId);
            if (!$asset) continue;

            // Hériter de la photo de l'équipement si disponible
            $photoPath = null;
            if ($asset->image && !str_starts_with($asset->image, 'http') && Storage::disk('public')->exists($asset->image)) {
                $ext      = pathinfo($asset->image, PATHINFO_EXTENSION);
                $destPath = 'computers/' . Str::uuid() . '.' . $ext;
                Storage::disk('public')->copy($asset->image, $destPath);
                $photoPath = $destPath;
            }

            Computer::create([
                'asset_id'      => $asset->id,
                'name'          => $asset->name,
                'asset_tag'     => $asset->asset_tag,
                'type'          => $data['type'],
                'os'            => $data['os'],
                'os_version'    => $data['os_version'] ?? null,
                'serial_number' => $asset->serial ?? null,
                'ip_addresses'  => null,
                'processor'     => $data['processor'] ?? null,
                'ram'           => $data['ram'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'photo'         => $photoPath,
            ]);
            $created++;
        }

        $message = $created === 1
            ? '1 poste créé avec succès.'
            : "{$created} postes créés avec succès.";

        if ($skipped > 0) {
            $message .= " ({$skipped} équipement(s) déjà associé(s) ignoré(s))";
        }

        return back()->with('success', $message);
    }

    /* ── Store ──────────────────────────────────────────────────────────── */

    public function store(Request $request)
    {
        $createMode  = $request->input('create_mode', 'manual');
        $ipAddresses = $this->parseIpAddresses($request);

        if ($createMode === 'from_asset') {
            $data = $request->validate([
                'asset_id'      => 'required|exists:assets,id',
                'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
                'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
                'os_version'    => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:255',
                'processor'     => 'nullable|string|max:255',
                'ram'           => 'nullable|string|max:50',
                'notes'         => 'nullable|string',
                'photo'         => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            ]);

            if (Computer::where('asset_id', $data['asset_id'])->exists()) {
                return back()->withErrors(['asset_id' => 'Cet équipement est déjà associé à un poste.']);
            }

            $asset = Asset::findOrFail($data['asset_id']);

            if ($request->hasFile('photo')) {
                $data['photo'] = $request->file('photo')->store('computers', 'public');
            } elseif ($asset->image && !str_starts_with($asset->image, 'http') && Storage::disk('public')->exists($asset->image)) {
                $ext      = pathinfo($asset->image, PATHINFO_EXTENSION);
                $destPath = 'computers/' . Str::uuid() . '.' . $ext;
                Storage::disk('public')->copy($asset->image, $destPath);
                $data['photo'] = $destPath;
            }

            Computer::create([
                'asset_id'      => $asset->id,
                'name'          => $asset->name,
                'asset_tag'     => $asset->asset_tag,
                'type'          => $data['type'],
                'os'            => $data['os'],
                'os_version'    => $data['os_version'] ?? null,
                'serial_number' => $data['serial_number'] ?? ($asset->serial ?? null),
                'ip_addresses'  => $ipAddresses ?: null,
                'processor'     => $data['processor'] ?? null,
                'ram'           => $data['ram'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'photo'         => $data['photo'] ?? null,
            ]);

        } else {
            $data = $request->validate([
                'name'          => 'required|string|max:255',
                'asset_tag'     => 'nullable|string|max:100|unique:computers,asset_tag',
                'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
                'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
                'os_version'    => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:255',
                'processor'     => 'nullable|string|max:255',
                'ram'           => 'nullable|string|max:50',
                'notes'         => 'nullable|string',
                'photo'         => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            ]);

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('computers', 'public');
            }

            $assetTag = $data['asset_tag'] ?: $this->generateAssetTag($data['name']);

            if (Asset::where('asset_tag', $assetTag)->exists()) {
                $assetTag = $assetTag . '-' . strtoupper(Str::random(3));
            }

            $asset = Asset::create([
                'name'          => $data['name'],
                'asset_tag'     => $assetTag,
                'status'        => 'available',
                'is_reservable' => false,
                'notes'         => $data['notes'] ?? null,
            ]);

            Computer::create([
                'asset_id'      => $asset->id,
                'name'          => $data['name'],
                'asset_tag'     => $assetTag,
                'type'          => $data['type'],
                'os'            => $data['os'],
                'os_version'    => $data['os_version'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'ip_addresses'  => $ipAddresses ?: null,
                'processor'     => $data['processor'] ?? null,
                'ram'           => $data['ram'] ?? null,
                'notes'         => $data['notes'] ?? null,
                'photo'         => $photoPath,
            ]);
        }

        return back()->with('success', 'Poste créé avec succès.');
    }

    /* ── Update ─────────────────────────────────────────────────────────── */

    public function update(Request $request, Computer $computer)
    {
        $isAdmin = auth()->user()->hasRole('admin');

        /* ── Mise à jour non-admin (champs restreints) ──────────────────── */
        if (!$isAdmin) {
            $user = auth()->user();
            $role = $user->hasRole('professeur') ? 'professeur'
                  : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

            if (Setting::get("section_computers_{$role}", 'false') !== 'true') {
                abort(403, 'Accès non autorisé.');
            }
            if (Setting::get("section_computers_edit_{$role}", 'false') !== 'true') {
                abort(403, 'Édition non autorisée.');
            }

            $updateData = [];
            $allFields  = ['ip_addresses', 'ram', 'processor', 'os_version', 'os', 'notes'];

            foreach ($allFields as $field) {
                if (Setting::get("section_computers_edit_field_{$field}_{$role}", 'false') !== 'true') {
                    continue;
                }
                if ($field === 'ip_addresses') {
                    $updateData['ip_addresses'] = $this->parseIpAddresses($request) ?: null;
                } else {
                    $updateData[$field] = $request->input($field);
                }
            }

            if (!empty($updateData)) {
                $computer->update($updateData);
            }

            return back()->with('success', 'Poste mis à jour.');
        }

        /* ── Mise à jour admin (complète) ───────────────────────────────── */
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_tag'     => 'nullable|string|max:100|unique:computers,asset_tag,' . $computer->id,
            'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version'    => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'processor'     => 'nullable|string|max:255',
            'ram'           => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'photo'         => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:5120',
            'remove_photo'  => 'boolean',
        ]);

        $ipAddresses = $this->parseIpAddresses($request);
        $data['ip_addresses'] = $ipAddresses ?: null;

        if ($request->boolean('remove_photo') && $computer->photo) {
            Storage::disk('public')->delete($computer->photo);
            $data['photo'] = null;
        }

        if ($request->hasFile('photo')) {
            if ($computer->photo) {
                Storage::disk('public')->delete($computer->photo);
            }
            $data['photo'] = $request->file('photo')->store('computers', 'public');
        }

        unset($data['remove_photo']);
        $computer->update($data);

        if ($computer->asset_id && $computer->asset) {
            $computer->asset->update(['name' => $data['name']]);
        }

        return back()->with('success', 'Poste mis à jour.');
    }

    /* ── Destroy ────────────────────────────────────────────────────────── */

    public function destroy(Computer $computer)
    {
        if ($computer->photo) {
            Storage::disk('public')->delete($computer->photo);
        }
        $computer->delete();
        return back()->with('success', 'Poste supprimé.');
    }

    /* ── Helpers ────────────────────────────────────────────────────────── */

    private function generateAssetTag(string $name): string
    {
        $slug = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $name), 0, 6));
        if (empty($slug)) $slug = 'PC';
        return $slug . '-' . strtoupper(Str::random(4));
    }
}
