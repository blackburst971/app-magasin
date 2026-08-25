<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetFileController extends Controller
{
    /** Uploader un fichier (image ou PDF) lié à un équipement */
    public function store(Request $request, Asset $asset)
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,avif,pdf',
            'name' => 'nullable|string|max:255',
        ]);

        $file     = $request->file('file');
        $mime     = $file->getMimeType();
        $type     = str_starts_with($mime ?? '', 'image/') ? 'image' : 'document';
        $path     = $file->store("assets/{$asset->id}/files", 'public');

        AssetFile::create([
            'asset_id'    => $asset->id,
            'name'        => $request->input('name') ?: $file->getClientOriginalName(),
            'path'        => $path,
            'mime_type'   => $mime,
            'size'        => $file->getSize(),
            'type'        => $type,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Fichier ajouté.');
    }

    /** Supprimer un fichier lié à un équipement */
    public function destroy(Asset $asset, AssetFile $file)
    {
        abort_if($file->asset_id !== $asset->id, 403);

        Storage::disk('public')->delete($file->path);
        $file->delete();

        return back()->with('success', 'Fichier supprimé.');
    }

    /** Renommer un fichier */
    public function update(Request $request, Asset $asset, AssetFile $file)
    {
        abort_if($file->asset_id !== $asset->id, 403);

        $request->validate(['name' => 'required|string|max:255']);
        $file->update(['name' => $request->input('name')]);

        return back()->with('success', 'Fichier renommé.');
    }
}
