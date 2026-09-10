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
        $fileName = $request->input('name') ?: $file->getClientOriginalName();

        // Si l'équipement a un modèle, propager le fichier à tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            // D'abord stocker le fichier pour l'asset courant
            $originalPath = $file->store("assets/{$asset->id}/files", 'public');

            AssetFile::create([
                'asset_id'    => $asset->id,
                'name'        => $fileName,
                'path'        => $originalPath,
                'mime_type'   => $mime,
                'size'        => $file->getSize(),
                'type'        => $type,
                'uploaded_by' => $request->user()->id,
            ]);

            // Ensuite copier le fichier pour tous les autres équipements du même modèle
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)
                ->where('id', '!=', $asset->id)
                ->orderBy('name')
                ->get();

            foreach ($assetsOfSameModel as $assetItem) {
                // Copier le fichier depuis le stockage vers le dossier de cet équipement
                $newPath = "assets/{$assetItem->id}/files/" . basename($originalPath);
                Storage::disk('public')->copy($originalPath, $newPath);

                AssetFile::create([
                    'asset_id'    => $assetItem->id,
                    'name'        => $fileName,
                    'path'        => $newPath,
                    'mime_type'   => $mime,
                    'size'        => $file->getSize(),
                    'type'        => $type,
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            $count = $assetsOfSameModel->count() + 1; // +1 pour l'asset courant
            return back()->with('success', "Fichier ajouté et propagé à {$count} équipement(s) du même modèle.");
        } else {
            // Pas de modèle : créer uniquement pour cet équipement
            $path = $file->store("assets/{$asset->id}/files", 'public');

            AssetFile::create([
                'asset_id'    => $asset->id,
                'name'        => $fileName,
                'path'        => $path,
                'mime_type'   => $mime,
                'size'        => $file->getSize(),
                'type'        => $type,
                'uploaded_by' => $request->user()->id,
            ]);

            return back()->with('success', 'Fichier ajouté.');
        }
    }

    /** Supprimer un fichier lié à un équipement */
    public function destroy(Asset $asset, AssetFile $file)
    {
        abort_if($file->asset_id !== $asset->id, 403);

        // Si l'équipement a un modèle, supprimer le fichier de tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)->get();

            foreach ($assetsOfSameModel as $assetItem) {
                // Trouver le fichier correspondant (même nom, type et taille)
                $correspondingFile = $assetItem->files()
                    ->where('name', $file->name)
                    ->where('mime_type', $file->mime_type)
                    ->where('size', $file->size)
                    ->first();

                if ($correspondingFile) {
                    Storage::disk('public')->delete($correspondingFile->path);
                    $correspondingFile->delete();
                }
            }

            $count = $assetsOfSameModel->count();
            return back()->with('success', "Fichier supprimé de {$count} équipement(s) du même modèle.");
        } else {
            // Pas de modèle : supprimer uniquement pour cet équipement
            Storage::disk('public')->delete($file->path);
            $file->delete();

            return back()->with('success', 'Fichier supprimé.');
        }
    }

    /** Renommer un fichier */
    public function update(Request $request, Asset $asset, AssetFile $file)
    {
        abort_if($file->asset_id !== $asset->id, 403);

        $request->validate(['name' => 'required|string|max:255']);
        $newName = $request->input('name');

        // Si l'équipement a un modèle, renommer le fichier pour tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            $oldName = $file->name;
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)->get();

            foreach ($assetsOfSameModel as $assetItem) {
                // Trouver le fichier correspondant (même ancien nom, type et taille)
                $correspondingFile = $assetItem->files()
                    ->where('name', $oldName)
                    ->where('mime_type', $file->mime_type)
                    ->where('size', $file->size)
                    ->first();

                if ($correspondingFile) {
                    $correspondingFile->update(['name' => $newName]);
                }
            }

            $count = $assetsOfSameModel->count();
            return back()->with('success', "Fichier renommé pour {$count} équipement(s) du même modèle.");
        } else {
            // Pas de modèle : renommer uniquement pour cet équipement
            $file->update(['name' => $newName]);
            return back()->with('success', 'Fichier renommé.');
        }
    }
}
