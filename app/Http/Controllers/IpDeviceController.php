<?php

namespace App\Http\Controllers;

use App\Models\IpDevice;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use League\Csv\Writer;

class IpDeviceController extends Controller
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

    /* ── Index ──────────────────────────────────────────────────────────── */

    public function index()
    {
        $isAdmin = auth()->user()->hasRole('admin');
        // Pas de restriction d'accès - tous les utilisateurs authentifiés peuvent voir les périphériques IP
        // $this->checkSectionAccess('ip_devices');

        $ipDevices = IpDevice::with(['place', 'asset'])
            ->orderBy('name')
            ->get()
            ->map(function ($device) use ($isAdmin) {
                return [
                    'id'            => $device->id,
                    'name'          => $device->name,
                    'device_type'   => $device->device_type,
                    'mac_address'   => $device->mac_address,
                    'ip_address'    => $device->ip_address,
                    'subnet_mask'   => $device->subnet_mask,
                    'gateway'       => $device->gateway,
                    'dns_primary'   => $device->dns_primary,
                    'dns_secondary' => $device->dns_secondary,
                    'place_id'      => $device->place_id,
                    'place_name'    => $device->place?->name,
                    'location'      => $device->location,
                    'brand'         => $device->brand,
                    'model'         => $device->model,
                    'notes'         => $isAdmin ? $device->notes : null,
                    'status'        => $device->status,
                    'asset_id'      => $device->asset_id,
                    'asset_is_reservable' => $device->asset?->is_reservable ?? false,
                    'photo_url'     => $device->photo ? asset('storage/' . $device->photo) : null,
                    'licenses'      => [],
                    'license_assignments_count' => 0,
                ];
            });

        // Récupérer les équipements disponibles (sans périphérique IP assigné)
        $availableAssets = $isAdmin
            ? \App\Models\Asset::whereDoesntHave('ipDevice')
                ->orderBy('name')
                ->get()
                ->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'serial'    => $a->serial,
                    'model_name' => $a->equipmentModel?->name ?? null,
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

        return Inertia::render('IpDevices/Index', [
            'ipDevices'          => $ipDevices,
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
        // Pas de restriction d'accès - tous les utilisateurs authentifiés peuvent voir l'adressage IP
        // $this->checkSectionAccess('ip_devices_ip');

        $ipDevices = IpDevice::with('place')
            ->orderBy('name')
            ->get()
            ->map(fn($device) => [
                'id'            => $device->id,
                'name'          => $device->name,
                'device_type'   => $device->device_type,
                'mac_address'   => $device->mac_address,
                'ip_address'    => $device->ip_address,
                'subnet_mask'   => $device->subnet_mask,
                'gateway'       => $device->gateway,
                'location'      => $device->location,
                'place_name'    => $device->place?->name,
                'brand'         => $device->brand,
                'model'         => $device->model,
            ]);

        return Inertia::render('IpDevices/IpAddressing', [
            'ipDevices' => $ipDevices,
            'isAdmin'   => $isAdmin,
        ]);
    }

    /* ── Export IP PDF ──────────────────────────────────────────────────── */

    public function exportIpPdf(Request $request)
    {
        $ipDevices = IpDevice::with('place')
            ->orderBy('name')
            ->get()
            ->map(fn($device) => [
                'name'          => $device->name,
                'device_type'   => $device->device_type,
                'mac_address'   => $device->mac_address,
                'ip_address'    => $device->ip_address,
                'subnet_mask'   => $device->subnet_mask,
                'gateway'       => $device->gateway,
                'location'      => $device->location,
                'place_name'    => $device->place?->name,
                'brand'         => $device->brand,
                'model'         => $device->model,
            ]);

        $pdf = Pdf::loadView('pdf.ip-addressing-export', compact('ipDevices'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('adressage-ip-' . now()->format('Y-m-d') . '.pdf');
    }

    /* ── Export IP CSV ──────────────────────────────────────────────────── */

    public function exportIpCsv(Request $request)
    {
        $ipDevices = IpDevice::with('place')
            ->orderBy('name')
            ->get();

        $csv = Writer::createFromString();
        $csv->setDelimiter(';');
        $csv->insertOne(["\xEF\xBB\xBF"]); // BOM UTF-8

        $csv->insertOne(['Nom', 'Type', 'Marque', 'Modèle', 'Adresse MAC', 'Adresse IP', 'Masque', 'Passerelle', 'DNS Primaire', 'DNS Secondaire', 'Lieu', 'Emplacement']);

        foreach ($ipDevices as $device) {
            $csv->insertOne([
                $device->name ?? '',
                $device->device_type ?? '',
                $device->brand ?? '',
                $device->model ?? '',
                $device->mac_address ?? '',
                $device->ip_address ?? '',
                $device->subnet_mask ?? '',
                $device->gateway ?? '',
                $device->dns_primary ?? '',
                $device->dns_secondary ?? '',
                $device->place?->name ?? '',
                $device->location ?? '',
            ]);
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
            'ids.*'      => 'required|integer|exists:ip_devices,id',
            'fields'     => 'required|array|min:1',
            'fields.*'   => 'in:device_type,status,place_id,brand,model,notes',
            'device_type' => 'nullable|string|max:255',
            'status'     => 'nullable|in:active,inactive,maintenance',
            'place_id'   => 'nullable|exists:places,id',
            'brand'      => 'nullable|string|max:255',
            'model'      => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $update = [];
        foreach ($data['fields'] as $field) {
            $update[$field] = $data[$field] ?? null;
        }

        IpDevice::whereIn('id', $data['ids'])->update($update);

        $count = count($data['ids']);
        return back()->with('success', "{$count} périphérique(s) IP mis à jour avec succès.");
    }

    /* ── Bulk Delete (suppression en masse — admin) ─────────────────────── */

    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:ip_devices,id',
        ]);

        IpDevice::whereIn('id', $data['ids'])->delete();

        $count = count($data['ids']);
        return back()->with('success', "{$count} périphérique(s) IP supprimé(s) avec succès.");
    }

    /* ── Store ──────────────────────────────────────────────────────────── */

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_id'      => 'nullable|exists:assets,id',
            'device_type'   => 'nullable|string|max:255',
            'mac_address'   => 'nullable|string|max:255',
            'ip_address'    => 'nullable|string|max:255',
            'subnet_mask'   => 'nullable|string|max:255',
            'gateway'       => 'nullable|string|max:255',
            'dns_primary'   => 'nullable|string|max:255',
            'dns_secondary' => 'nullable|string|max:255',
            'place_id'      => 'nullable|exists:places,id',
            'location'      => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:active,inactive,maintenance',
        ]);

        // Default values
        $data['subnet_mask'] = $data['subnet_mask'] ?? '255.255.255.0';
        $data['status'] = $data['status'] ?? 'active';

        IpDevice::create($data);

        return back()->with('success', 'Périphérique IP créé avec succès.');
    }

    /* ── Batch Store (création depuis l'inventaire) ──────────────────────── */

    public function batchStore(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $data = $request->validate([
            'asset_ids'     => 'required|array|min:1',
            'asset_ids.*'   => 'required|integer|exists:assets,id',
            'device_type'   => 'nullable|string|max:255',
            'mac_address'   => 'nullable|string|max:255',
            'ip_address'    => 'nullable|string|max:255',
            'subnet_mask'   => 'nullable|string|max:255',
            'gateway'       => 'nullable|string|max:255',
            'dns_primary'   => 'nullable|string|max:255',
            'dns_secondary' => 'nullable|string|max:255',
            'notes'         => 'nullable|string',
        ]);

        $count = 0;
        foreach ($data['asset_ids'] as $assetId) {
            $asset = \App\Models\Asset::find($assetId);
            if (!$asset) continue;

            // Vérifier que cet asset n'a pas déjà un périphérique IP
            if ($asset->ipDevice) continue;

            IpDevice::create([
                'asset_id'      => $asset->id,
                'name'          => $asset->name,
                'device_type'   => $data['device_type'] ?? null,
                'mac_address'   => $data['mac_address'] ?? null,
                'ip_address'    => $data['ip_address'] ?? null,
                'subnet_mask'   => $data['subnet_mask'] ?? '255.255.255.0',
                'gateway'       => $data['gateway'] ?? null,
                'dns_primary'   => $data['dns_primary'] ?? null,
                'dns_secondary' => $data['dns_secondary'] ?? null,
                'place_id'      => $asset->place_id ?? null,
                'location'      => $asset->location ?? null,
                'brand'         => $asset->equipmentModel?->manufacturer?->name ?? null,
                'model'         => $asset->equipmentModel?->name ?? null,
                'notes'         => $data['notes'] ?? null,
                'status'        => 'active',
                'photo'         => $asset->image ?? null,
            ]);

            $count++;
        }

        return back()->with('success', "{$count} périphérique(s) IP créé(s) depuis l'inventaire.");
    }

    /* ── Update ─────────────────────────────────────────────────────────── */

    public function update(Request $request, IpDevice $ipDevice)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'device_type'   => 'nullable|string|max:255',
            'mac_address'   => 'nullable|string|max:255',
            'ip_address'    => 'nullable|string|max:255',
            'subnet_mask'   => 'nullable|string|max:255',
            'gateway'       => 'nullable|string|max:255',
            'dns_primary'   => 'nullable|string|max:255',
            'dns_secondary' => 'nullable|string|max:255',
            'place_id'      => 'nullable|exists:places,id',
            'location'      => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'notes'         => 'nullable|string',
            'status'        => 'nullable|in:active,inactive,maintenance',
        ]);

        $ipDevice->update($data);

        return back()->with('success', 'Périphérique IP mis à jour.');
    }

    /* ── Demande de changement IP (utilisateurs non-admin) ───────────────── */

    public function requestIpChange(Request $request, IpDevice $ipDevice)
    {
        $user = auth()->user();

        $data = $request->validate([
            'ip_address'      => 'nullable|string|max:255',
            'subnet_mask'     => 'nullable|string|max:255',
            'gateway'         => 'nullable|string|max:255',
            'dns_primary'     => 'nullable|string|max:255',
            'dns_secondary'   => 'nullable|string|max:255',
            'ip_change_reason'=> 'nullable|string',
        ]);

        // Vérifier qu'au moins un champ a changé
        $hasChanges = ($data['ip_address'] !== $ipDevice->ip_address)
            || ($data['subnet_mask'] !== $ipDevice->subnet_mask)
            || ($data['gateway'] !== $ipDevice->gateway)
            || ($data['dns_primary'] !== $ipDevice->dns_primary)
            || ($data['dns_secondary'] !== $ipDevice->dns_secondary);

        if (!$hasChanges) {
            return back()->with('info', 'Aucune modification réseau détectée.');
        }

        // Créer une demande de changement réseau
        \App\Models\IpDeviceIpChangeRequest::create([
            'ip_device_id'      => $ipDevice->id,
            'requested_by'      => auth()->id(),
            'old_ip_address'    => $ipDevice->ip_address,
            'old_subnet_mask'   => $ipDevice->subnet_mask,
            'old_gateway'       => $ipDevice->gateway,
            'old_dns_primary'   => $ipDevice->dns_primary,
            'old_dns_secondary' => $ipDevice->dns_secondary,
            'new_ip_address'    => $data['ip_address'],
            'new_subnet_mask'   => $data['subnet_mask'],
            'new_gateway'       => $data['gateway'],
            'new_dns_primary'   => $data['dns_primary'],
            'new_dns_secondary' => $data['dns_secondary'],
            'reason'            => $data['ip_change_reason'] ?? null,
            'status'            => 'pending',
        ]);

        // Notifier les admins
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\IpDeviceIpChangeRequested($ipDevice, $user));
        }

        return back()->with('success', 'Demande de changement réseau envoyée. Un administrateur la validera prochainement.');
    }

    /* ── Destroy ────────────────────────────────────────────────────────── */

    public function destroy(IpDevice $ipDevice)
    {
        $ipDevice->delete();
        return back()->with('success', 'Périphérique IP supprimé.');
    }

    /* ── Gestion des demandes de changement d'IP ─────────────────────────── */

    public function ipChangeRequests()
    {
        // Rediriger vers la page unifiée des demandes IP (Computers + IP Devices)
        return redirect()->route('computers.ip-change-requests');
    }

    public function getIpChangeRequests()
    {
        $requests = \App\Models\IpDeviceIpChangeRequest::with(['ipDevice', 'requestedBy', 'reviewedBy'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }

    public function approveIpChangeRequest(\App\Models\IpDeviceIpChangeRequest $changeRequest)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $ipDevice = $changeRequest->ipDevice;

        // Appliquer les changements réseau
        $ipDevice->update([
            'ip_address'    => $changeRequest->new_ip_address,
            'subnet_mask'   => $changeRequest->new_subnet_mask,
            'gateway'       => $changeRequest->new_gateway,
            'dns_primary'   => $changeRequest->new_dns_primary,
            'dns_secondary' => $changeRequest->new_dns_secondary,
        ]);

        // Marquer la demande comme approuvée et archivée automatiquement
        $changeRequest->update([
            'status' => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'archived' => true,
            'archived_at' => now(),
            'archived_by' => auth()->id(),
        ]);

        // Notifier le demandeur
        $changeRequest->requestedBy->notify(new \App\Notifications\IpDeviceIpChangeReviewed($changeRequest, 'approved'));

        return back()->with('success', 'Changement réseau approuvé, appliqué et archivé.');
    }

    public function rejectIpChangeRequest(\App\Models\IpDeviceIpChangeRequest $changeRequest, Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Action non autorisée.');
        }

        $data = $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        // Marquer la demande comme rejetée et archivée automatiquement
        $changeRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
            'archived' => true,
            'archived_at' => now(),
            'archived_by' => auth()->id(),
        ]);

        // Notifier le demandeur
        $changeRequest->requestedBy->notify(new \App\Notifications\IpDeviceIpChangeReviewed($changeRequest, 'rejected'));

        return back()->with('success', 'Demande de changement réseau rejetée et archivée.');
    }
}
