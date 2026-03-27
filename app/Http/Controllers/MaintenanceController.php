<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Maintenance;
use App\Http\Controllers\WaitlistController;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Maintenance::with(['asset', 'createdBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        $maintenances = $query->get()->map(fn($m) => [
            'id'                  => $m->id,
            'asset_id'            => $m->asset_id,
            'asset_name'          => $m->asset->name ?? 'N/A',
            'asset_tag'           => $m->asset->asset_tag ?? 'N/A',
            'title'               => $m->title,
            'description'         => $m->description,
            'provider'            => $m->provider,
            'cost'                => $m->cost,
            'started_at'          => $m->started_at?->format('d/m/Y'),
            'expected_return_at'  => $m->expected_return_at?->format('d/m/Y'),
            'resolved_at'         => $m->resolved_at?->format('d/m/Y H:i'),
            'status'              => $m->status,
            'status_label'        => $m->status_label,
            'status_color'        => $m->status_color,
            'resolution_notes'    => $m->resolution_notes,
            'created_by_name'     => $m->createdBy->name ?? 'N/A',
            'created_at'          => $m->created_at->format('d/m/Y'),
        ]);

        $assets = Asset::orderBy('name')->get(['id', 'name', 'asset_tag']);

        return Inertia::render('Maintenance/Index', [
            'maintenances' => $maintenances,
            'assets'       => $assets,
            'filters'      => $request->only(['status', 'asset_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'           => 'required|exists:assets,id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'provider'           => 'nullable|string|max:255',
            'cost'               => 'nullable|numeric|min:0',
            'started_at'         => 'required|date',
            'expected_return_at' => 'nullable|date|after_or_equal:started_at',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'in_progress';

        $maintenance = Maintenance::create($data);

        // Passer l'asset en maintenance
        $asset = Asset::find($data['asset_id']);
        $asset->update(['status' => 'maintenance']);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => $request->user()->id,
            'action'   => 'maintenance',
            'notes'    => "Mise en maintenance — {$maintenance->title}",
        ]);

        return back()->with('success', "Fiche maintenance créée. L'équipement est passé en maintenance.");
    }

    public function update(Request $request, Maintenance $maintenance)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'provider'           => 'nullable|string|max:255',
            'cost'               => 'nullable|numeric|min:0',
            'started_at'         => 'required|date',
            'expected_return_at' => 'nullable|date',
            'status'             => 'required|in:pending,in_progress,resolved',
        ]);

        $maintenance->update($data);

        return back()->with('success', 'Fiche maintenance mise à jour.');
    }

    public function resolve(Request $request, Maintenance $maintenance)
    {
        $data = $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $maintenance->update(array_merge($data, [
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]));

        // Remettre l'asset en disponible
        $asset = $maintenance->asset;
        $asset->update(['status' => 'available']);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => $request->user()->id,
            'action'   => 'status_change',
            'notes'    => "Maintenance résolue — {$maintenance->title}" . ($data['resolution_notes'] ? " — {$data['resolution_notes']}" : ''),
        ]);

        // Notifier la liste d'attente
        WaitlistController::notifyWaitlist($asset);

        return back()->with('success', "Maintenance résolue. L'équipement est de nouveau disponible.");
    }

    public function destroy(Maintenance $maintenance)
    {
        $maintenance->delete();

        return back()->with('success', 'Fiche maintenance supprimée.');
    }
}
