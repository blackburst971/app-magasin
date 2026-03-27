<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\Computer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class LicenseController extends Controller
{
    public function index()
    {
        $licenses = License::with(['assignments.computer', 'supplier'])
            ->withCount('assignments')
            ->orderBy('name')
            ->get()
            ->map(function ($l) {
                return [
                    'id'               => $l->id,
                    'name'             => $l->name,
                    'version'          => $l->version,
                    'manufacturer'     => $l->manufacturer,
                    'license_key'      => $l->license_key,
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
                    'assignments'      => $l->assignments->map(fn($a) => [
                        'id'          => $a->id,
                        'computer'    => $a->computer ? [
                            'id'       => $a->computer->id,
                            'name'     => $a->computer->name,
                            'type'     => $a->computer->type,
                            'os'       => $a->computer->os,
                            'photo_url'=> $a->computer->photo ? Storage::url($a->computer->photo) : null,
                        ] : null,
                        'notes'       => $a->notes,
                        'assigned_at' => $a->assigned_at?->format('Y-m-d'),
                    ]),
                ];
            });

        $computers = Computer::orderBy('name')->get(['id', 'name', 'type', 'os']);
        $suppliers  = Supplier::orderBy('name')->get(['id', 'name']);

        $stats = [
            'total'         => $licenses->count(),
            'seats_total'   => $licenses->whereNotNull('seats')->sum('seats'),
            'seats_used'    => $licenses->sum('seats_used'),
            'expiring_soon' => $licenses->where('is_expiring_soon', true)->count(),
            'expired'       => $licenses->where('is_expired', true)->count(),
            'cloud'         => $licenses->where('is_cloud', true)->count(),
        ];

        return Inertia::render('Licenses/Index', compact('licenses', 'computers', 'suppliers', 'stats'));
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
        ]);

        License::create($data);

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
        ]);

        $license->update($data);

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
            'assigned_at' => 'required|date',
            'notes'       => 'nullable|string',
        ]);

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
                'assigned_by' => auth()->id(),
                'assigned_at' => $data['assigned_at'],
                'notes'       => $data['notes'] ?? null,
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['computer_id' => 'Cette licence est déjà attribuée à ce poste.']);
        }

        return back()->with('success', 'Licence attribuée avec succès.');
    }

    public function unassign(License $license, LicenseAssignment $assignment)
    {
        abort_if($assignment->license_id !== $license->id, 403);
        $assignment->delete();
        return back()->with('success', 'Attribution supprimée.');
    }
}
