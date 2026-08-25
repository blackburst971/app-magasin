<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::withCount(['assets', 'equipmentModels'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Suppliers', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'address'      => 'nullable|string|max:1000',
            'website'      => 'nullable|url|max:255',
            'notes'        => 'nullable|string|max:2000',
        ]);

        Supplier::create($data);

        return back()->with('success', 'Fournisseur créé avec succès.');
    }

    /** Création rapide depuis un formulaire inline (retourne JSON). */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $supplier = Supplier::firstOrCreate(['name' => trim($data['name'])]);
        return response()->json(['id' => $supplier->id, 'name' => $supplier->name]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'address'      => 'nullable|string|max:1000',
            'website'      => 'nullable|url|max:255',
            'notes'        => 'nullable|string|max:2000',
        ]);

        $supplier->update($data);

        return back()->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return back()->with('success', 'Fournisseur supprimé.');
    }
}
