<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories', [
            'categories' => Category::withCount('assets')->orderBy('name')->get()
                ->map(fn($c) => array_merge($c->toArray(), [
                    'image_url' => $c->image ? Storage::url($c->image) : null,
                ])),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'color'       => 'nullable|string|max:7',
            'image'       => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        Category::create($data);
        return back()->with('success', 'Catégorie créée.');
    }

    /** Création rapide depuis un formulaire inline (retourne JSON). */
    public function quickStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:categories',
        ]);

        $category = Category::create($data);
        return response()->json(['id' => $category->id, 'name' => $category->name]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => "required|string|max:255|unique:categories,name,{$category->id}",
            'description' => 'nullable|string',
            'color'       => 'nullable|string|max:7',
            'image'       => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);
        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(Request $request, Category $category)
    {
        // Réassignation optionnelle avant suppression (évite les liens orphelins)
        $reassignTo = $request->input('reassign_to');
        if ($reassignTo && $reassignTo != $category->id) {
            $targetExists = Category::where('id', $reassignTo)->exists();
            if ($targetExists) {
                Asset::where('category_id', $category->id)->update(['category_id' => $reassignTo]);
            }
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }
        $category->delete();
        return back()->with('success', 'Catégorie supprimée.');
    }
}
