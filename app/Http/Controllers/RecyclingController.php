<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\RecyclingItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use League\Csv\Writer;

class RecyclingController extends Controller
{
    public function index()
    {
        $items = RecyclingItem::with(['asset', 'createdBy'])
            ->latest()
            ->get()
            ->map(fn($item) => [
                'id'            => $item->id,
                'asset_id'      => $item->asset_id,
                'asset_tag'     => $item->asset?->asset_tag,
                'designation'   => $item->designation,
                'serial_number' => $item->serial_number,
                'reference'     => $item->reference,
                'brand'         => $item->brand,
                'model_name'    => $item->model_name,
                'quantity'      => $item->quantity,
                'notes'         => $item->notes,
                'photo_url'     => $item->photo_url,
                'extra_fields'  => $item->extra_fields ?? [],
                'created_by'    => $item->createdBy?->name,
                'created_at'    => $item->created_at->format('d/m/Y'),
            ]);

        $assets = Asset::orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'serial'])
            ->map(fn($a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'asset_tag'     => $a->asset_tag,
                'serial_number' => $a->serial ?? null,
            ]);

        return Inertia::render('Maintenance/Recycling', [
            'items'  => $items,
            'assets' => $assets,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id'      => 'nullable|exists:assets,id',
            'designation'   => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model_name'    => 'nullable|string|max:255',
            'quantity'      => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:2000',
            'photo'         => 'nullable|image|max:8192',
            'extra_fields'  => 'nullable|array',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('recycling', 'public');
        }

        RecyclingItem::create([
            'asset_id'           => $data['asset_id'] ?? null,
            'designation'        => $data['designation'],
            'serial_number'      => $data['serial_number'] ?? null,
            'reference'          => $data['reference'] ?? null,
            'brand'              => $data['brand'] ?? null,
            'model_name'         => $data['model_name'] ?? null,
            'quantity'           => $data['quantity'],
            'notes'              => $data['notes'] ?? null,
            'photo_path'         => $photoPath,
            'extra_fields'       => !empty($data['extra_fields']) ? $data['extra_fields'] : null,
            'created_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Équipement ajouté à la liste déchèterie.');
    }

    public function update(Request $request, RecyclingItem $recyclingItem)
    {
        $data = $request->validate([
            'designation'   => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model_name'    => 'nullable|string|max:255',
            'quantity'      => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:2000',
            'photo'         => 'nullable|image|max:8192',
            'remove_photo'  => 'nullable|boolean',
            'extra_fields'  => 'nullable|array',
        ]);

        $photoPath = $recyclingItem->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath) Storage::disk('public')->delete($photoPath);
            $photoPath = $request->file('photo')->store('recycling', 'public');
        } elseif (!empty($data['remove_photo'])) {
            if ($photoPath) Storage::disk('public')->delete($photoPath);
            $photoPath = null;
        }

        $recyclingItem->update([
            'designation'   => $data['designation'],
            'serial_number' => $data['serial_number'] ?? null,
            'reference'     => $data['reference'] ?? null,
            'brand'         => $data['brand'] ?? null,
            'model_name'    => $data['model_name'] ?? null,
            'quantity'      => $data['quantity'],
            'notes'         => $data['notes'] ?? null,
            'photo_path'    => $photoPath,
            'extra_fields'  => !empty($data['extra_fields']) ? $data['extra_fields'] : null,
        ]);

        return back()->with('success', 'Fiche mise à jour.');
    }

    public function destroy(RecyclingItem $recyclingItem)
    {
        if ($recyclingItem->photo_path) {
            Storage::disk('public')->delete($recyclingItem->photo_path);
        }
        $recyclingItem->delete();
        return back()->with('success', 'Fiche supprimée.');
    }

    public function uploadPhoto(Request $request, RecyclingItem $recyclingItem)
    {
        $request->validate(['photo' => 'required|image|max:8192']);

        if ($recyclingItem->photo_path) {
            Storage::disk('public')->delete($recyclingItem->photo_path);
        }
        $path = $request->file('photo')->store('recycling', 'public');
        $recyclingItem->update(['photo_path' => $path]);

        return response()->json(['photo_url' => Storage::url($path)]);
    }

    public function exportPdf(Request $request)
    {
        $ids = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:recycling_items,id',
        ])['ids'];

        $items = RecyclingItem::whereIn('id', $ids)->get();

        // Collect all unique extra field keys
        $extraKeys = [];
        foreach ($items as $item) {
            foreach (array_keys($item->extra_fields ?? []) as $key) {
                if (!in_array($key, $extraKeys)) $extraKeys[] = $key;
            }
        }

        $pdf = Pdf::loadView('pdf.recycling-export', compact('items', 'extraKeys'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('liste-decheterie-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportCsv(Request $request)
    {
        $ids = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'exists:recycling_items,id',
        ])['ids'];

        $items = RecyclingItem::whereIn('id', $ids)->get();

        $extraKeys = [];
        foreach ($items as $item) {
            foreach (array_keys($item->extra_fields ?? []) as $key) {
                if (!in_array($key, $extraKeys)) $extraKeys[] = $key;
            }
        }

        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Writer::BOM_UTF8);
        $csv->setDelimiter(';');

        $headers = ['Designation', 'Reference', 'N° Serie', 'Marque', 'Modele', 'Quantite', 'Notes'];
        $csv->insertOne(array_merge($headers, $extraKeys));

        foreach ($items as $item) {
            $row = [
                $item->designation,
                $item->reference     ?? '',
                $item->serial_number ?? '',
                $item->brand         ?? '',
                $item->model_name    ?? '',
                $item->quantity,
                $item->notes         ?? '',
            ];
            foreach ($extraKeys as $key) {
                $row[] = $item->extra_fields[$key] ?? '';
            }
            $csv->insertOne($row);
        }

        return response($csv->toString(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="liste-decheterie-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
