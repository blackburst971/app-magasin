<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ComputerController extends Controller
{
    public function index()
    {
        $computers = Computer::withCount('licenseAssignments')
            ->with(['licenseAssignments.license'])
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                return [
                    'id'                       => $c->id,
                    'name'                     => $c->name,
                    'asset_tag'                => $c->asset_tag,
                    'type'                     => $c->type,
                    'os'                       => $c->os,
                    'os_version'               => $c->os_version,
                    'serial_number'            => $c->serial_number,
                    'ip_address'               => $c->ip_address,
                    'processor'                => $c->processor,
                    'ram'                      => $c->ram,
                    'notes'                    => $c->notes,
                    'photo_url'                => $c->photo ? Storage::url($c->photo) : null,
                    'license_assignments_count'=> $c->license_assignments_count,
                    'licenses'                 => $c->licenseAssignments->map(fn($a) => [
                        'id'          => $a->id,
                        'license_id'  => $a->license_id,
                        'name'        => $a->license->name,
                        'version'     => $a->license->version,
                        'type'        => $a->license->type,
                        'is_cloud'    => $a->license->is_cloud,
                        'expiry_date' => $a->license->expiry_date?->format('Y-m-d'),
                        'is_expired'  => $a->license->is_expired,
                        'notes'       => $a->notes,
                        'assigned_at' => $a->assigned_at?->format('Y-m-d'),
                    ]),
                ];
            });

        return Inertia::render('Computers/Index', ['computers' => $computers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_tag'     => 'nullable|string|max:100|unique:computers,asset_tag',
            'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version'    => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'ip_address'    => 'nullable|string|max:50',
            'processor'     => 'nullable|string|max:255',
            'ram'           => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'photo'         => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('computers', 'public');
        }
        unset($data['photo_file']);

        Computer::create($data);

        return back()->with('success', 'Poste créé avec succès.');
    }

    public function update(Request $request, Computer $computer)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_tag'     => 'nullable|string|max:100|unique:computers,asset_tag,' . $computer->id,
            'type'          => 'required|in:desktop,laptop,imac,macbook,mac_mini,mac_studio,server,tablet,other',
            'os'            => 'required|in:windows_10,windows_11,windows_server,macos,linux,chromeos,other',
            'os_version'    => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'ip_address'    => 'nullable|string|max:50',
            'processor'     => 'nullable|string|max:255',
            'ram'           => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'photo'         => 'nullable|image|max:5120',
            'remove_photo'  => 'boolean',
        ]);

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

        return back()->with('success', 'Poste mis à jour.');
    }

    public function destroy(Computer $computer)
    {
        if ($computer->photo) {
            Storage::disk('public')->delete($computer->photo);
        }
        $computer->delete();
        return back()->with('success', 'Poste supprimé.');
    }
}
