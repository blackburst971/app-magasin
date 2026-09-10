<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\RecyclingItem;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use League\Csv\Writer;

class RecyclingController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────
    // ACCÈS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Vérifie que l'utilisateur courant peut accéder à la déchèterie
     * (admin toujours, non-admin uniquement si section_recycling_{role} = true).
     */
    private function checkAccess(): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return;

        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        if (Setting::get("section_recycling_{$role}", 'false') !== 'true') {
            abort(403, 'Accès non autorisé à la déchèterie.');
        }
    }

    /**
     * Retourne true si l'utilisateur peut modifier/supprimer la fiche
     * (admin ou créateur de la fiche).
     */
    private function canEdit(RecyclingItem $item): bool
    {
        $user = Auth::user();
        return $user->hasRole('admin') || $item->created_by_user_id === $user->id;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->checkAccess();

        $user    = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $mapItem = fn($item) => [
            'id'                   => $item->id,
            'asset_id'             => $item->asset_id,
            'asset_tag'            => $item->asset?->asset_tag,
            'designation'          => $item->designation,
            'serial_number'        => $item->serial_number,
            'reference'            => $item->reference,
            'brand'                => $item->brand,
            'model_name'           => $item->model_name,
            'quantity'             => $item->quantity,
            'notes'                => $item->notes,
            'photo_url'            => $item->photo_url,
            'extra_fields'         => $item->extra_fields ?? [],
            'created_by'           => $item->createdBy?->name,
            'created_by_user_id'   => $item->created_by_user_id,
            'created_at'           => $item->created_at->format('d/m/Y H:i'),
            'archived_at'          => $item->archived_at?->format('d/m/Y H:i'),
            'archived_by'          => $item->archivedBy?->name,
        ];

        $items = RecyclingItem::with(['asset', 'createdBy'])
            ->whereNull('archived_at')
            ->latest()
            ->get()
            ->map($mapItem);

        $archivedItems = $isAdmin
            ? RecyclingItem::with(['asset', 'createdBy', 'archivedBy'])
                ->whereNotNull('archived_at')
                ->latest('archived_at')
                ->get()
                ->map($mapItem)
            : collect();

        $assets = Asset::orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'serial'])
            ->map(fn($a) => [
                'id'            => $a->id,
                'name'          => $a->name,
                'asset_tag'     => $a->asset_tag,
                'serial_number' => $a->serial ?? null,
            ]);

        return Inertia::render('Maintenance/Recycling', [
            'items'         => $items,
            'archivedItems' => $archivedItems,
            'assets'        => $assets,
            'isAdmin'       => $isAdmin,
            'myUserId'      => $user->id,
        ]);
    }

    public function store(Request $request)
    {
        $this->checkAccess();

        $data = $request->validate([
            'asset_id'      => 'nullable|exists:assets,id',
            'designation'   => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model_name'    => 'nullable|string|max:255',
            'quantity'      => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:2000',
            'photo'         => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:8192',
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

        // Si un équipement est lié, le passer automatiquement en statut "retiré"
        if (!empty($data['asset_id'])) {
            $asset = Asset::find($data['asset_id']);
            if ($asset) {
                $asset->update(['status' => 'retired']);
            }
        }

        return back()->with('success', 'Équipement ajouté à la liste déchèterie.');
    }

    public function update(Request $request, RecyclingItem $recyclingItem)
    {
        $this->checkAccess();

        if (!$this->canEdit($recyclingItem)) {
            abort(403, 'Vous ne pouvez modifier que vos propres fiches.');
        }

        $data = $request->validate([
            'designation'   => 'required|string|max:255',
            'serial_number' => 'nullable|string|max:255',
            'reference'     => 'nullable|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'model_name'    => 'nullable|string|max:255',
            'quantity'      => 'required|integer|min:1',
            'notes'         => 'nullable|string|max:2000',
            'photo'         => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:8192',
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
        $this->checkAccess();

        if (!$this->canEdit($recyclingItem)) {
            abort(403, 'Vous ne pouvez supprimer que vos propres fiches.');
        }

        if ($recyclingItem->photo_path) {
            Storage::disk('public')->delete($recyclingItem->photo_path);
        }
        $recyclingItem->delete();
        return back()->with('success', 'Fiche supprimée.');
    }

    public function uploadPhoto(Request $request, RecyclingItem $recyclingItem)
    {
        $this->checkAccess();

        if (!$this->canEdit($recyclingItem)) {
            abort(403, 'Vous ne pouvez modifier que vos propres fiches.');
        }

        $request->validate(['photo' => 'required|image|max:8192']);

        if ($recyclingItem->photo_path) {
            Storage::disk('public')->delete($recyclingItem->photo_path);
        }
        $path = $request->file('photo')->store('recycling', 'public');
        $recyclingItem->update(['photo_path' => $path]);

        return response()->json(['photo_url' => Storage::url($path)]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ARCHIVAGE — admin uniquement
    // ─────────────────────────────────────────────────────────────────────

    public function bulkArchive(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:recycling_items,id',
        ]);

        $count = RecyclingItem::whereIn('id', $data['ids'])
            ->whereNull('archived_at')
            ->update([
                'archived_at'          => now(),
                'archived_by_user_id'  => Auth::id(),
            ]);

        return back()->with('success', "{$count} équipement(s) archivé(s).");
    }

    public function unarchive(RecyclingItem $recyclingItem)
    {
        $recyclingItem->update([
            'archived_at'         => null,
            'archived_by_user_id' => null,
        ]);

        return back()->with('success', "Équipement « {$recyclingItem->designation} » restauré dans la liste active.");
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPORTS — admin uniquement
    // ─────────────────────────────────────────────────────────────────────

    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');

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

        // Encoder les photos en base64 pour dompdf, redimensionnées pour limiter la mémoire
        $photoData = [];
        foreach ($items as $item) {
            if ($item->photo_path && !str_starts_with($item->photo_path, 'http')) {
                try {
                    $disk    = Storage::disk('public');
                    $absPath = $disk->path($item->photo_path);

                    if (function_exists('imagecreatefromstring') && file_exists($absPath)) {
                        $src = @imagecreatefromstring(file_get_contents($absPath));
                        if ($src) {
                            // Redimensionner à 200×160 max pour le PDF
                            $maxW = 200; $maxH = 160;
                            $origW = imagesx($src); $origH = imagesy($src);
                            $ratio = min($maxW / $origW, $maxH / $origH, 1);
                            $newW  = (int) round($origW * $ratio);
                            $newH  = (int) round($origH * $ratio);
                            $dst   = imagecreatetruecolor($newW, $newH);
                            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                            imagedestroy($src);
                            ob_start();
                            imagejpeg($dst, null, 80);
                            imagedestroy($dst);
                            $jpeg = ob_get_clean();
                            $photoData[$item->id] = 'data:image/jpeg;base64,' . base64_encode($jpeg);
                            continue;
                        }
                    }
                    // Fallback : encodage direct sans redimensionnement
                    $content = $disk->get($item->photo_path);
                    $mime    = $disk->mimeType($item->photo_path) ?: 'image/jpeg';
                    $photoData[$item->id] = 'data:' . $mime . ';base64,' . base64_encode($content);
                } catch (\Exception $e) {
                    $photoData[$item->id] = null;
                }
            } else {
                $photoData[$item->id] = null;
            }
        }

        $pdf = Pdf::loadView('pdf.recycling-export', compact('items', 'extraKeys', 'photoData'))
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

        $headers = ['Designation', 'Reference', 'N° Serie', 'Marque', 'Modele', 'Quantite', 'Notes', 'Photo URL'];
        $csv->insertOne(array_merge($headers, $extraKeys));

        foreach ($items as $item) {
            $photoUrl = $item->photo_path
                ? (str_starts_with($item->photo_path, 'http')
                    ? $item->photo_path
                    : Storage::disk('public')->url($item->photo_path))
                : '';

            $row = [
                $item->designation,
                $item->reference     ?? '',
                $item->serial_number ?? '',
                $item->brand         ?? '',
                $item->model_name    ?? '',
                $item->quantity,
                $item->notes         ?? '',
                $photoUrl,
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
