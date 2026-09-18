<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\LicenseKey;
use App\Models\Computer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index()
    {
        $isAdmin = auth()->user()->hasRole('admin');

        $licenses = License::with(['assignments.computer', 'assignments.user', 'supplier', 'keys.assignedToComputer'])
            ->withCount('assignments')
            ->orderBy('name')
            ->get()
            ->map(function ($l) use ($isAdmin) {
                return [
                    'id'               => $l->id,
                    'name'             => $l->name,
                    'version'          => $l->version,
                    'manufacturer'     => $l->manufacturer,
                    'license_key'      => $isAdmin ? $l->license_key : null,
                    'seats'            => $l->seats,
                    'seats_used'       => $l->assignments_count,
                    'type'             => $l->type,
                    'is_cloud'         => $l->is_cloud,
                    'purchase_date'    => $l->purchase_date?->format('Y-m-d'),
                    'expiry_date'      => $l->expiry_date?->format('Y-m-d'),
                    'is_expired'       => $l->is_expired,
                    'is_expiring_soon' => $l->is_expiring_soon,
                    'purchase_cost'    => $l->purchase_cost,
                    'order_number'     => $l->order_number,
                    'supplier'         => $l->supplier ? ['id' => $l->supplier->id, 'name' => $l->supplier->name] : null,
                    'notes'            => $l->notes,
                    'keys'             => $l->keys->map(fn($k) => [
                        'id'            => $k->id,
                        'key_code'      => $isAdmin ? $k->key_code : null,
                        'status'        => $k->status,
                        'assigned_to'   => $k->assignedToComputer ? [
                            'id'   => $k->assignedToComputer->id,
                            'name' => $k->assignedToComputer->name,
                        ] : null,
                        'assigned_at'   => $k->assigned_at?->format('Y-m-d H:i'),
                        'notes'         => $k->notes,
                    ]),
                    'assignments'      => $l->assignments->map(fn($a) => [
                        'id'          => $a->id,
                        'computer'    => $a->computer ? [
                            'id'       => $a->computer->id,
                            'name'     => $a->computer->name,
                            'type'     => $a->computer->type,
                            'os'       => $a->computer->os,
                            'photo_url'=> $a->computer->photo ? Storage::url($a->computer->photo) : null,
                        ] : null,
                        'user'        => $a->user ? [
                            'id'       => $a->user->id,
                            'name'     => $a->user->name,
                            'email'    => $a->user->email,
                        ] : null,
                        'notes'       => $a->notes,
                        'assigned_at' => $a->assigned_at?->format('Y-m-d'),
                    ]),
                ];
            });

        $computers = Computer::orderBy('name')->get(['id', 'name', 'type', 'os']);
        $users     = \App\Models\User::orderBy('name')->get(['id', 'name', 'email']);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $stats = [
            'total'         => $licenses->count(),
            'seats_total'   => $licenses->whereNotNull('seats')->sum('seats'),
            'seats_used'    => $licenses->sum('seats_used'),
            'expiring_soon' => $licenses->where('is_expiring_soon', true)->count(),
            'expired'       => $licenses->where('is_expired', true)->count(),
            'cloud'         => $licenses->where('is_cloud', true)->count(),
        ];

        return Inertia::render('Licenses/Index', compact('licenses', 'computers', 'users', 'suppliers', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'version'       => 'nullable|string|max:100',
            'manufacturer'  => 'nullable|string|max:255',
            'license_key'   => 'nullable|string',
            'seats'         => 'nullable|integer|min:1',
            'type'          => 'required|in:per_seat,volume,oem,cloud,dongle,open_source',
            'is_cloud'      => 'boolean',
            'purchase_date' => 'nullable|date',
            'expiry_date'   => 'nullable|date|after_or_equal:purchase_date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'order_number'  => 'nullable|string|max:100',
            'supplier_id'   => 'nullable|exists:suppliers,id',
            'notes'         => 'nullable|string',
            'license_keys'  => 'nullable|array',
            'license_keys.*.key_code' => 'required|string|max:255',
            'license_keys.*.notes'    => 'nullable|string',
        ]);

        // Normaliser les chaînes vides en null pour les champs nullable FK
        foreach (['supplier_id', 'purchase_date', 'expiry_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $licenseKeys = $data['license_keys'] ?? [];
        unset($data['license_keys']);

        $license = License::create($data);

        // Créer les clés de licence
        foreach ($licenseKeys as $keyData) {
            $license->keys()->create([
                'key_code' => $keyData['key_code'],
                'status'   => 'available',
                'notes'    => $keyData['notes'] ?? null,
            ]);
        }

        return back()->with('success', 'Licence créée avec succès.');
    }

    public function update(Request $request, License $license)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'version'       => 'nullable|string|max:100',
            'manufacturer'  => 'nullable|string|max:255',
            'license_key'   => 'nullable|string',
            'seats'         => 'nullable|integer|min:1',
            'type'          => 'required|in:per_seat,volume,oem,cloud,dongle,open_source',
            'is_cloud'      => 'boolean',
            'purchase_date' => 'nullable|date',
            'expiry_date'   => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'order_number'  => 'nullable|string|max:100',
            'supplier_id'   => 'nullable|exists:suppliers,id',
            'notes'         => 'nullable|string',
            'license_keys'  => 'nullable|array',
            'license_keys.*.id'       => 'nullable|exists:license_keys,id',
            'license_keys.*.key_code' => 'required|string|max:255',
            'license_keys.*.notes'    => 'nullable|string',
        ]);

        // Normaliser les chaînes vides en null pour les champs nullable FK
        foreach (['supplier_id', 'purchase_date', 'expiry_date'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        $licenseKeys = $data['license_keys'] ?? [];
        unset($data['license_keys']);

        $license->update($data);

        // Gérer les clés de licence
        $submittedKeyIds = [];
        foreach ($licenseKeys as $keyData) {
            if (isset($keyData['id'])) {
                // Mise à jour d'une clé existante
                $key = $license->keys()->find($keyData['id']);
                if ($key) {
                    $key->update([
                        'key_code' => $keyData['key_code'],
                        'notes'    => $keyData['notes'] ?? null,
                    ]);
                    $submittedKeyIds[] = $keyData['id'];
                }
            } else {
                // Nouvelle clé
                $newKey = $license->keys()->create([
                    'key_code' => $keyData['key_code'],
                    'status'   => 'available',
                    'notes'    => $keyData['notes'] ?? null,
                ]);
                $submittedKeyIds[] = $newKey->id;
            }
        }

        // Supprimer les clés qui ne sont plus dans la liste (seulement si disponibles)
        $license->keys()
            ->whereNotIn('id', $submittedKeyIds)
            ->where('status', 'available')
            ->delete();

        return back()->with('success', 'Licence mise à jour.');
    }

    public function destroy(License $license)
    {
        $license->delete();
        return back()->with('success', 'Licence supprimée.');
    }

    /* ── Attributions ───────────────────────────────────────────────────── */

    public function assign(Request $request, License $license)
    {
        $data = $request->validate([
            'computer_id' => 'nullable|exists:computers,id',
            'user_id'     => 'nullable|exists:users,id',
            'assigned_at' => 'required|date',
            'notes'       => 'nullable|string',
        ]);

        // Validation : une licence doit être attribuée soit à un poste, soit à un utilisateur (mais pas les deux)
        if (empty($data['computer_id']) && empty($data['user_id'])) {
            return back()->withErrors(['assignment' => 'Vous devez sélectionner un poste informatique OU un utilisateur.']);
        }
        if (!empty($data['computer_id']) && !empty($data['user_id'])) {
            return back()->withErrors(['assignment' => 'Vous ne pouvez pas attribuer une licence à la fois à un poste et à un utilisateur.']);
        }

        // Vérifier les sièges disponibles
        if ($license->seats !== null) {
            $used = $license->assignments()->count();
            if ($used >= $license->seats) {
                return back()->withErrors(['seats' => 'Plus de sièges disponibles pour cette licence.']);
            }
        }

        try {
            LicenseAssignment::create([
                'license_id'  => $license->id,
                'computer_id' => $data['computer_id'] ?? null,
                'user_id'     => $data['user_id'] ?? null,
                'assigned_by' => auth()->id(),
                'assigned_at' => $data['assigned_at'],
                'notes'       => $data['notes'] ?? null,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['assignment' => 'Cette licence est déjà attribuée.']);
        }

        $assignedTo = !empty($data['computer_id']) ? 'poste informatique' : 'utilisateur';
        return back()->with('success', "Licence attribuée au $assignedTo avec succès.");
    }

    public function unassign(License $license, LicenseAssignment $assignment)
    {
        abort_if($assignment->license_id !== $license->id, 403);
        $assignment->delete();
        return back()->with('success', 'Attribution supprimée.');
    }
}
