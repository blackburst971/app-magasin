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
            ->with(['licenseAssignments.license', 'asset', 'place'])
            ->orderBy('name')
            ->get()
            ->map(function ($c) use ($isAdmin) {
                return [
                    'id'                        => $c->id,
                    'asset_id'                  => $c->asset_id,
                    'asset_tag_asset'           => $c->asset?->asset_tag,
                    'asset_is_reservable'       => $c->asset ? (bool) $c->asset->is_reservable : null,
                    'name'                      => $c->name,
                    'place_id'                  => $c->place_id,
                    'place_name'                => $c->place?->name,
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
                    'licenses'                  => $c->licenseAssignments->map(function($a) use ($isAdmin) {
                        $data = [
                            'id'          => $a->id,
                            'license_id'  => $a->license_id,
                            'name'        => $a->license->name,
                            'version'     => $a->license->version,
                            'type'        => $a->license->type,
                            'is_cloud'    => $a->license->is_cloud,
                            'expiry_date' => $a->license->expiry_date?->format('Y-m-d'),
                            'is_expired'  => $a->license->is_expired,
                        ];
                        // Informations sensibles uniquement pour les admins
                        if ($isAdmin) {
                            $data['license_key'] = $a->license->license_key;
                            $data['notes'] = $a->notes;
                            $data['assigned_at'] = $a->assigned_at?->format('Y-m-d');
                        }
                        return $data;
                    }),
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

        // Pour les non-admins : pas de modification directe, uniquement demande de changement IP
        $canEdit = false;
        $canRequestIpChange = !$isAdmin; // Les non-admins peuvent demander un changement d'IP
        $editableFields = []; // Aucun champ directement modifiable pour les non-admins

        // Récupérer tous les lieux pour le formulaire admin
        $places = $isAdmin
            ? \App\Models\Place::orderBy('name')->get()->map(fn($p) => [
                'id'   => $p->id,
                'name' => $p->name,
            ])
            : [];

        return Inertia::render('Computers/Index', [
            'computers'          => $computers,
            'availableAssets'    => $availableAssets,
            'places'             => $places,
            'isAdmin'            => $isAdmin,
            'canEdit'            => $canEdit,
            'canRequestIpChange' => $canRequestIpChange,
            'editableFields'     => $editableFields,
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
                'place_id'      => $asset->place_id ?? null,
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
                'mac_address'   => 'nullable|string|max:17',
                'subnet_mask'   => 'nullable|string|max:15',
                'gateway'       => 'nullable|string|max:15',
                'dns_primary'   => 'nullable|string|max:15',
                'dns_secondary' => 'nullable|string|max:15',
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
                'place_id'      => $asset->place_id ?? null,
                'name'          => $asset->name,
                'asset_tag'     => $asset->asset_tag,
                'type'          => $data['type'],
                'os'            => $data['os'],
                'os_version'    => $data['os_version'] ?? null,
                'serial_number' => $data['serial_number'] ?? ($asset->serial ?? null),
                'ip_addresses'  => $ipAddresses ?: null,
                'mac_address'   => $data['mac_address'] ?? null,
                'subnet_mask'   => $data['subnet_mask'] ?? '255.255.255.0',
                'gateway'       => $data['gateway'] ?? null,
                'dns_primary'   => $data['dns_primary'] ?? null,
                'dns_secondary' => $data['dns_secondary'] ?? null,
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
                'mac_address'   => 'nullable|string|max:17',
                'subnet_mask'   => 'nullable|string|max:15',
                'gateway'       => 'nullable|string|max:15',
                'dns_primary'   => 'nullable|string|max:15',
                'dns_secondary' => 'nullable|string|max:15',
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
                'mac_address'   => $data['mac_address'] ?? null,
                'subnet_mask'   => $data['subnet_mask'] ?? '255.255.255.0',
                'gateway'       => $data['gateway'] ?? null,
                'dns_primary'   => $data['dns_primary'] ?? null,
                'dns_secondary' => $data['dns_secondary'] ?? null,
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
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        /* ── Mise à jour admin (complète) ───────────────────────────────── */
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'place_id'      => 'nullable|exists:places,id',
            'asset_tag'     => 'nullable|string|max:100|unique:computers,asset_tag,' . $computer->id,
            'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version'    => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'mac_address'   => 'nullable|string|max:17',
            'subnet_mask'   => 'nullable|string|max:15',
            'gateway'       => 'nullable|string|max:15',
            'dns_primary'   => 'nullable|string|max:15',
            'dns_secondary' => 'nullable|string|max:15',
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

        // Ne pas synchroniser le nom du Computer avec l'Asset
        // Le nom du Computer sert à décrire sa fonction/rôle

        return back()->with('success', 'Poste mis à jour.');
    }

    /* ── Demande de changement IP (utilisateurs non-admin) ───────────────── */

    public function requestIpChange(Request $request, Computer $computer)
    {
        $user = auth()->user();

        // Vérifier si l'utilisateur tente de modifier les paramètres réseau
        $newIpAddresses = $this->parseIpAddresses($request);
        $newSubnetMask = $request->input('subnet_mask');
        $newGateway = $request->input('gateway');
        $newDnsPrimary = $request->input('dns_primary');
        $newDnsSecondary = $request->input('dns_secondary');

        // Vérifier qu'au moins un champ réseau a changé
        $hasChanges = ($newIpAddresses !== $computer->ip_addresses)
            || ($newSubnetMask !== $computer->subnet_mask)
            || ($newGateway !== $computer->gateway)
            || ($newDnsPrimary !== $computer->dns_primary)
            || ($newDnsSecondary !== $computer->dns_secondary);

        if (!$hasChanges) {
            return back()->with('info', 'Aucune modification réseau détectée.');
        }

        // Créer une demande de changement réseau
        \App\Models\ComputerIpChangeRequest::create([
            'computer_id' => $computer->id,
            'requested_by' => auth()->id(),
            'old_ip_addresses' => $computer->ip_addresses ?? [],
            'new_ip_addresses' => $newIpAddresses ?? [],
            'old_subnet_mask' => $computer->subnet_mask,
            'new_subnet_mask' => $newSubnetMask,
            'old_gateway' => $computer->gateway,
            'new_gateway' => $newGateway,
            'old_dns_primary' => $computer->dns_primary,
            'new_dns_primary' => $newDnsPrimary,
            'old_dns_secondary' => $computer->dns_secondary,
            'new_dns_secondary' => $newDnsSecondary,
            'status' => 'pending',
            'notes' => $request->input('ip_change_notes'),
        ]);

        // Notifier les admins
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\ComputerIpChangeRequested($computer, $user));
        }

        return back()->with('success', 'Demande de changement réseau envoyée. Un administrateur la validera prochainement.');
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

    /* ── Gestion des demandes de changement d'IP ─────────────────────────── */

    public function ipChangeRequests()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $requests = \App\Models\ComputerIpChangeRequest::with(['computer', 'requestedBy', 'reviewedBy'])
            ->orderBy('status', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => [
                'id'                => $r->id,
                'computer_id'       => $r->computer_id,
                'computer_name'     => $r->computer?->name,
                'requested_by_name' => $r->requestedBy?->name,
                'old_ip_addresses'  => $r->old_ip_addresses,
                'new_ip_addresses'  => $r->new_ip_addresses,
                'old_subnet_mask'   => $r->old_subnet_mask,
                'new_subnet_mask'   => $r->new_subnet_mask,
                'old_gateway'       => $r->old_gateway,
                'new_gateway'       => $r->new_gateway,
                'old_dns_primary'   => $r->old_dns_primary,
                'new_dns_primary'   => $r->new_dns_primary,
                'old_dns_secondary' => $r->old_dns_secondary,
                'new_dns_secondary' => $r->new_dns_secondary,
                'status'            => $r->status,
                'notes'             => $r->notes,
                'created_at'        => $r->created_at?->format('Y-m-d H:i'),
                'reviewed_by_name'  => $r->reviewedBy?->name,
                'reviewed_at'       => $r->reviewed_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Computers/IpChangeRequests', [
            'requests' => $requests,
        ]);
    }

    public function getIpChangeRequests()
    {
        $requests = \App\Models\ComputerIpChangeRequest::with(['computer', 'requestedBy', 'reviewedBy'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }

    public function approveIpChangeRequest(\App\Models\ComputerIpChangeRequest $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $computer = $request->computer;

        // Appliquer les changements réseau
        $computer->update([
            'ip_addresses' => $request->new_ip_addresses,
            'subnet_mask' => $request->new_subnet_mask,
            'gateway' => $request->new_gateway,
            'dns_primary' => $request->new_dns_primary,
            'dns_secondary' => $request->new_dns_secondary,
        ]);

        // Marquer la demande comme approuvée
        $request->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Notifier le demandeur
        $request->requestedBy->notify(new \App\Notifications\ComputerIpChangeReviewed($request, 'approved'));

        return back()->with('success', 'Changement réseau approuvé et appliqué.');
    }

    public function rejectIpChangeRequest(\App\Models\ComputerIpChangeRequest $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        // Marquer la demande comme rejetée
        $request->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Notifier le demandeur
        $request->requestedBy->notify(new \App\Notifications\ComputerIpChangeReviewed($request, 'rejected'));

        return back()->with('success', 'Demande de changement d\'adresse IP rejetée.');
    }
}
