<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\EquipmentModel;
use App\Models\EquipmentModelDocument;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EquipmentModelController extends Controller
{
    public function index()
    {
        $models = EquipmentModel::with(['category', 'documents'])->withCount('assets')->orderBy('name')->get()
            ->map(fn($m) => array_merge($m->toArray(), [
                'image_url'    => $m->image_url,
                'assets_count' => $m->assets_count,
                'documents'    => $m->documents->map(fn($d) => [
                    'id'            => $d->id,
                    'name'          => $d->name,
                    'original_name' => $d->original_name,
                    'url'           => $d->url,
                    'size'          => $d->size,
                    'formatted_size'=> $d->formatted_size,
                ]),
            ]));

        return Inertia::render('Models/Index', [
            'models'        => $models,
            'categories'    => Category::orderBy('name')->get(),
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name']),
            'suppliers'     => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'manufacturer'    => 'nullable|string|max:255',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'category_id'     => 'nullable|exists:categories,id',
            'supplier_id'     => 'nullable|exists:suppliers,id',
            'description'     => 'nullable|string',
            'image'           => 'nullable|image|max:2048',
            'quantity'        => 'nullable|integer|min:0|max:500',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('models', 'public');
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        unset($data['quantity']);

        // Résoudre manufacturer_id depuis le texte si besoin
        $data = $this->resolveManufacturerId($data);

        $model = EquipmentModel::create($data);

        if ($model->image) {
            $model->propagateImageToAssets();
        }

        // Auto-create assets if quantity > 0
        if ($quantity > 0) {
            $this->createAssetsForModel($model, $quantity);
        }

        return back()->with('success', "Modèle créé avec succès" . ($quantity > 0 ? " — {$quantity} équipement(s) créé(s)." : '.'));
    }

    public function addAssets(Request $request, EquipmentModel $model)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:500',
        ]);

        $this->createAssetsForModel($model, $data['quantity']);

        return back()->with('success', "{$data['quantity']} équipement(s) ajouté(s) au modèle « {$model->name} ».");
    }

    private function createAssetsForModel(EquipmentModel $model, int $quantity): void
    {
        // Start numbering after existing assets to avoid duplicates like "#1", "#1"
        $existingCount = $model->assets()->count();

        for ($i = 1; $i <= $quantity; $i++) {
            $tag = 'EQ-' . strtoupper(substr(str_pad(base_convert(mt_rand(0, 1679615), 10, 36), 5, '0', STR_PAD_LEFT), 0, 5));

            // Ensure unique tag
            $attempt = 0;
            while (Asset::where('asset_tag', $tag)->exists() && $attempt < 10) {
                $tag = 'EQ-' . strtoupper(substr(str_pad(base_convert(mt_rand(0, 1679615), 10, 36), 5, '0', STR_PAD_LEFT), 0, 5));
                $attempt++;
            }

            Asset::create([
                'name'               => "{$model->name} #" . ($existingCount + $i),
                'asset_tag'          => $tag,
                'equipment_model_id' => $model->id,
                'category_id'        => $model->category_id,
                'manufacturer'       => $model->manufacturer,
                'manufacturer_id'    => $model->manufacturer_id,  // ← propagation correcte
                'status'             => 'available',
                'is_reservable'      => true,
                'image'              => $model->image,
            ]);
        }
    }

    /**
     * Résout manufacturer_id à partir du champ texte "manufacturer" si non fourni.
     * Crée l'entrée Manufacturer si elle n'existe pas encore.
     */
    private function resolveManufacturerId(array $data): array
    {
        if (empty($data['manufacturer_id']) && !empty($data['manufacturer'])) {
            $mfr = Manufacturer::firstOrCreate(['name' => trim($data['manufacturer'])]);
            $data['manufacturer_id'] = $mfr->id;
        }
        return $data;
    }

    public function update(Request $request, EquipmentModel $model)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'manufacturer'    => 'nullable|string|max:255',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'category_id'     => 'nullable|exists:categories,id',
            'supplier_id'     => 'nullable|exists:suppliers,id',
            'description'     => 'nullable|string',
            'image'           => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($model->image && !str_starts_with($model->image, 'http')) {
                Storage::disk('public')->delete($model->image);
            }
            $data['image'] = $request->file('image')->store('models', 'public');
        }

        $data = $this->resolveManufacturerId($data);

        $oldManufacturerId = $model->manufacturer_id;
        $model->update($data);

        // Propager manufacturer_id et manufacturer aux assets du modèle
        if (($data['manufacturer_id'] ?? null) !== $oldManufacturerId) {
            $model->assets()->update([
                'manufacturer_id' => $model->manufacturer_id,
                'manufacturer'    => $model->manufacturer,
            ]);
        }

        if (isset($data['image'])) {
            $model->propagateImageToAssets();
        }

        return back()->with('success', 'Modèle mis à jour. Fabricant propagé à tous les équipements liés.');
    }

    public function duplicate(EquipmentModel $model)
    {
        EquipmentModel::create([
            'name'         => $model->name . ' (copie)',
            'manufacturer' => $model->manufacturer,
            'category_id'  => $model->category_id,
            'description'  => $model->description,
            'image'        => $model->image,  // référence partagée à la même image
        ]);

        return back()->with('success', "Modèle « {$model->name} » dupliqué avec succès.");
    }

    public function destroy(EquipmentModel $model)
    {
        // Supprimer les images et fichiers physiques de chaque équipement lié
        foreach ($model->assets as $asset) {
            if ($asset->image && !str_starts_with($asset->image, 'http')) {
                Storage::disk('public')->delete($asset->image);
            }
        }

        // Supprimer tous les équipements liés à ce modèle
        $deletedCount = $model->assets()->count();
        $model->assets()->delete();

        // Supprimer l'image du modèle
        if ($model->image && !str_starts_with($model->image, 'http')) {
            Storage::disk('public')->delete($model->image);
        }

        // Les documents sont supprimés en cascade (cascadeOnDelete en DB)
        // mais on supprime aussi les fichiers physiques
        foreach ($model->documents as $doc) {
            Storage::disk('public')->delete($doc->path);
        }

        $model->delete();

        $msg = $deletedCount > 0
            ? "Modèle supprimé avec {$deletedCount} équipement(s) associé(s)."
            : 'Modèle supprimé.';

        return back()->with('success', $msg);
    }

    // ─── DOCUMENTS PDF ────────────────────────────────────────────────────
    public function uploadDocument(Request $request, EquipmentModel $model)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480', // 20 Mo max
            'name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $path = $file->store('model_docs', 'public');

        $document = EquipmentModelDocument::create([
            'equipment_model_id' => $model->id,
            'name'               => $data['name'] ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'original_name'      => $file->getClientOriginalName(),
            'path'               => $path,
            'size'               => $file->getSize(),
        ]);

        return back()->with('success', 'Document ajouté.');
    }

    public function deleteDocument(EquipmentModel $model, EquipmentModelDocument $document)
    {
        // Vérifier que le document appartient bien à ce modèle
        if ($document->equipment_model_id !== $model->id) {
            abort(403);
        }

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }
}
