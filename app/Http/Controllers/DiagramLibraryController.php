<?php

namespace App\Http\Controllers;

use App\Models\DiagramLibraryItem;
use Illuminate\Http\Request;

class DiagramLibraryController extends Controller
{
    /** Liste toutes les images de la bibliothèque partagée. */
    public function index()
    {
        return response()->json(
            DiagramLibraryItem::orderBy('created_at')->get(['id', 'name', 'src'])
        );
    }

    /** Ajoute une image à la bibliothèque (data URL base64). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'src'  => 'required|string',
        ]);

        $item = DiagramLibraryItem::create($data);

        return response()->json([
            'id'   => $item->id,
            'name' => $item->name,
            'src'  => $item->src,
        ], 201);
    }

    /** Supprime une image de la bibliothèque. */
    public function destroy(DiagramLibraryItem $diagramLibraryItem)
    {
        $diagramLibraryItem->delete();
        return response()->json(['ok' => true]);
    }
}
