<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ManufacturerController extends Controller
{
    public function index()
    {
        return Inertia::render('Manufacturers/Index', [
            'manufacturers' => Manufacturer::withCount(['equipmentModels', 'assets'])
                ->orderBy('name')
                ->get()
                ->map(fn($m) => array_merge($m->toArray(), [
                    'image_url' => $m->image_url,
                ])),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website'     => 'nullable|url|max:255',
            'image'       => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('manufacturers', 'public');
        }

        Manufacturer::create($data);
        return back()->with('success', 'Fabricant créé.');
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website'     => 'nullable|url|max:255',
            'image'       => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($manufacturer->image && !str_starts_with($manufacturer->image, 'http')) {
                Storage::disk('public')->delete($manufacturer->image);
            }
            $data['image'] = $request->file('image')->store('manufacturers', 'public');
        }

        $manufacturer->update($data);
        return back()->with('success', 'Fabricant mis à jour.');
    }

    public function destroy(Manufacturer $manufacturer)
    {
        if ($manufacturer->image && !str_starts_with($manufacturer->image, 'http')) {
            Storage::disk('public')->delete($manufacturer->image);
        }
        $manufacturer->delete();
        return back()->with('success', 'Fabricant supprimé.');
    }
}
