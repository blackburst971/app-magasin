<?php

namespace App\Http\Controllers;

use App\Models\ProcurementFile;
use App\Models\ProcurementFormField;
use App\Models\ProcurementHistory;
use App\Models\ProcurementItem;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use League\Csv\Writer;

class ProcurementController extends Controller
{
    private function checkAccess(string $level = 'read'): void
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return;

        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        if (Setting::get("section_procurement_{$role}_{$level}", 'false') !== 'true') {
            abort(403, 'Accès non autorisé aux commandes.');
        }
    }

    private function canWrite(): bool
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return true;

        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        return Setting::get("section_procurement_{$role}_write", 'false') === 'true';
    }

    private function canExport(): bool
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) return true;

        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        return Setting::get("section_procurement_{$role}_export", 'false') === 'true';
    }

    private function mapItem(ProcurementItem $item): array
    {
        return [
            'id'                  => $item->id,
            'designation'         => $item->designation,
            'type'                => $item->type,
            'type_label'          => ProcurementItem::typeLabel($item->type),
            'quantity'            => $item->quantity,
            'unit'                => $item->unit,
            'reference'           => $item->reference,
            'manufacturer'        => $item->manufacturer,
            'supplier'            => $item->supplier,
            'unit_price'          => $item->unit_price,
            'total_price'         => $item->total_price,
            'product_url'         => $item->product_url,
            'notes'               => $item->notes,
            'extra_fields'        => $item->extra_fields,
            'status'              => $item->status,
            'status_label'        => ProcurementItem::statusLabel($item->status),
            'priority'            => $item->priority,
            'priority_label'      => ProcurementItem::priorityLabel($item->priority),
            'created_by'          => $item->createdBy?->name,
            'created_by_user_id'  => $item->created_by_user_id,
            'created_at'          => $item->created_at->format('d/m/Y H:i'),
            'updated_by'          => $item->updatedBy?->name,
            'updated_at'          => $item->updated_at && $item->updated_at->ne($item->created_at)
                                      ? $item->updated_at->format('d/m/Y H:i')
                                      : null,
            'archived_at'         => $item->archived_at?->format('d/m/Y H:i'),
            'archived_by'         => $item->archivedBy?->name,
        ];
    }

    public function index()
    {
        $this->checkAccess('read');

        $user    = Auth::user();
        $isAdmin = $user->hasRole('admin');

        $items = ProcurementItem::with(['createdBy', 'updatedBy'])
            ->whereNull('archived_at')
            ->latest()
            ->get()
            ->map(fn($item) => $this->mapItem($item));

        $archivedItems = [];
        if ($isAdmin) {
            $archivedItems = ProcurementItem::with(['createdBy', 'updatedBy', 'archivedBy'])
                ->whereNotNull('archived_at')
                ->latest('archived_at')
                ->get()
                ->map(fn($item) => $this->mapItem($item));
        }

        $formFields = ProcurementFormField::where('active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn($f) => [
                'id'         => $f->id,
                'label'      => $f->label,
                'type'       => $f->type,
                'options'    => $f->options,
                'required'   => $f->required,
                'sort_order' => $f->sort_order,
                'active'     => $f->active,
            ]);

        $allFormFields = [];
        if ($isAdmin) {
            $allFormFields = ProcurementFormField::orderBy('sort_order')
                ->get()
                ->map(fn($f) => [
                    'id'         => $f->id,
                    'label'      => $f->label,
                    'type'       => $f->type,
                    'options'    => $f->options,
                    'required'   => $f->required,
                    'sort_order' => $f->sort_order,
                    'active'     => $f->active,
                ]);
        }

        $files = ProcurementFile::with('uploadedBy')
            ->latest()
            ->get()
            ->map(fn($f) => [
                'id'                  => $f->id,
                'original_name'       => $f->original_name,
                'description'         => $f->description,
                'file_size_human'     => $f->file_size_human,
                'mime_type'           => $f->mime_type,
                'icon_type'           => $f->icon_type,
                'download_url'        => $f->download_url,
                'uploaded_by'         => $f->uploadedBy?->name,
                'uploaded_by_user_id' => $f->uploaded_by_user_id,
                'created_at'          => $f->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Procurement/Index', [
            'items'         => $items,
            'archivedItems' => $archivedItems,
            'formFields'    => $formFields,
            'allFormFields' => $allFormFields,
            'files'         => $files,
            'isAdmin'       => $isAdmin,
            'canWrite'      => $this->canWrite(),
            'canExport'     => $this->canExport(),
            'myUserId'      => Auth::id(),
        ]);
    }

    public function store(Request $request)
    {
        $this->checkAccess('write');

        $data = $request->validate([
            'designation'  => 'required|string|max:255',
            'type'         => 'required|in:materiel,consommable,logiciel,autre',
            'quantity'     => 'required|integer|min:1',
            'unit'         => 'nullable|string|max:50',
            'reference'    => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'supplier'     => 'nullable|string|max:255',
            'unit_price'   => 'nullable|numeric|min:0',
            'product_url'  => 'nullable|url|max:2048',
            'notes'        => 'nullable|string|max:2000',
            'status'       => 'required|in:besoin,en_commande,commande,livre,annule',
            'priority'     => 'required|in:haute,normale,basse',
            'extra_fields' => 'nullable|array',
        ]);

        $item = ProcurementItem::create([
            ...$data,
            'unit'               => $data['unit'] ?? 'unité',
            'created_by_user_id' => Auth::id(),
        ]);

        ProcurementHistory::create([
            'procurement_item_id' => $item->id,
            'user_id'             => Auth::id(),
            'action'              => 'created',
            'snapshot'            => $item->toArray(),
            'changes'             => null,
        ]);

        return back()->with('success', 'Besoin ajouté.');
    }

    public function update(Request $request, ProcurementItem $procurementItem)
    {
        $this->checkAccess('write');

        $user = Auth::user();
        if (!$user->hasRole('admin') && $procurementItem->created_by_user_id !== Auth::id()) {
            abort(403, 'Vous ne pouvez modifier que vos propres articles.');
        }

        $data = $request->validate([
            'designation'  => 'required|string|max:255',
            'type'         => 'required|in:materiel,consommable,logiciel,autre',
            'quantity'     => 'required|integer|min:1',
            'unit'         => 'nullable|string|max:50',
            'reference'    => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'supplier'     => 'nullable|string|max:255',
            'unit_price'   => 'nullable|numeric|min:0',
            'product_url'  => 'nullable|url|max:2048',
            'notes'        => 'nullable|string|max:2000',
            'status'       => 'required|in:besoin,en_commande,commande,livre,annule',
            'priority'     => 'required|in:haute,normale,basse',
            'extra_fields' => 'nullable|array',
        ]);

        $old = $procurementItem->toArray();
        $procurementItem->update([
            ...$data,
            'unit'                => $data['unit'] ?? 'unité',
            'updated_by_user_id'  => Auth::id(),
        ]);

        $changes = [];
        foreach ($data as $field => $newVal) {
            $oldVal = $old[$field] ?? null;
            if ((string)$oldVal !== (string)$newVal) {
                $changes[] = ['field' => $field, 'before' => $oldVal, 'after' => $newVal];
            }
        }

        ProcurementHistory::create([
            'procurement_item_id' => $procurementItem->id,
            'user_id'             => Auth::id(),
            'action'              => 'updated',
            'snapshot'            => $procurementItem->fresh()->toArray(),
            'changes'             => $changes ?: null,
        ]);

        return back()->with('success', 'Besoin mis à jour.');
    }

    public function destroy(ProcurementItem $procurementItem)
    {
        $this->checkAccess('write');

        $user = Auth::user();
        if (!$user->hasRole('admin') && $procurementItem->created_by_user_id !== Auth::id()) {
            abort(403, 'Vous ne pouvez supprimer que vos propres articles.');
        }

        $procurementItem->delete();
        return back()->with('success', 'Besoin supprimé.');
    }

    public function bulkArchive(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) abort(403);

        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:procurement_items,id',
        ]);

        $items = ProcurementItem::whereIn('id', $request->ids)
            ->whereNull('archived_at')
            ->get();

        $count = $items->count();
        foreach ($items as $item) {
            $item->update([
                'archived_at'          => now(),
                'archived_by_user_id'  => Auth::id(),
            ]);
            ProcurementHistory::create([
                'procurement_item_id' => $item->id,
                'user_id'             => Auth::id(),
                'action'              => 'archived',
                'snapshot'            => $item->fresh()->toArray(),
                'changes'             => null,
            ]);
        }

        return back()->with('success', "{$count} besoin(s) archivé(s).");
    }

    public function unarchive(ProcurementItem $procurementItem)
    {
        if (!Auth::user()->hasRole('admin')) abort(403);

        $procurementItem->update([
            'archived_at'          => null,
            'archived_by_user_id'  => null,
        ]);

        ProcurementHistory::create([
            'procurement_item_id' => $procurementItem->id,
            'user_id'             => Auth::id(),
            'action'              => 'unarchived',
            'snapshot'            => $procurementItem->fresh()->toArray(),
            'changes'             => null,
        ]);

        return back()->with('success', 'Besoin restauré.');
    }

    public function exportPdf(Request $request)
    {
        if (!$this->canExport()) abort(403);
        ini_set('memory_limit', '256M');

        $ids   = $request->input('ids');
        $query = ProcurementItem::whereNull('archived_at')->orderBy('created_at');
        if ($ids) $query->whereIn('id', $ids);
        $items = $query->get();

        $total      = $items->sum(fn($i) => $i->total_price ?? 0);
        $formFields = ProcurementFormField::where('active', true)->orderBy('sort_order')->get();

        $pdf = Pdf::loadView('pdf.procurement-export', compact('items', 'total', 'formFields'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('commandes-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportCsv(Request $request)
    {
        if (!$this->canExport()) abort(403);

        $ids   = $request->input('ids');
        $query = ProcurementItem::whereNull('archived_at')->orderBy('created_at');
        if ($ids) $query->whereIn('id', $ids);
        $items = $query->get();

        $formFields = ProcurementFormField::where('active', true)->orderBy('sort_order')->get();

        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Writer::BOM_UTF8);
        $csv->setDelimiter(';');

        $header = ['Désignation', 'Type', 'Qté', 'Unité', 'Référence', 'Fabricant', 'Fournisseur', 'Prix unitaire', 'Total', 'Statut', 'Priorité', 'Lien', 'Notes'];
        foreach ($formFields as $ff) {
            $header[] = $ff->label;
        }
        $csv->insertOne($header);

        foreach ($items as $item) {
            $row = [
                $item->designation,
                ProcurementItem::typeLabel($item->type),
                $item->quantity,
                $item->unit,
                $item->reference    ?? '',
                $item->manufacturer ?? '',
                $item->supplier     ?? '',
                $item->unit_price   !== null ? number_format($item->unit_price, 2, ',', ' ') : '',
                $item->total_price  !== null ? number_format($item->total_price, 2, ',', ' ') : '',
                ProcurementItem::statusLabel($item->status),
                ProcurementItem::priorityLabel($item->priority),
                $item->product_url  ?? '',
                $item->notes        ?? '',
            ];
            foreach ($formFields as $ff) {
                $row[] = $item->extra_fields[$ff->label] ?? '';
            }
            $csv->insertOne($row);
        }

        return response($csv->toString(), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="commandes-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function getFormFields()
    {
        if (!Auth::user()->hasRole('admin')) abort(403);
        return response()->json(ProcurementFormField::orderBy('sort_order')->get());
    }

    public function saveFormFields(Request $request)
    {
        if (!Auth::user()->hasRole('admin')) abort(403);

        $request->validate([
            'fields'              => 'required|array',
            'fields.*.id'         => 'nullable|integer',
            'fields.*.label'      => 'required|string|max:255',
            'fields.*.type'       => 'required|string|in:text,number,textarea,url,date,select,radio,checkbox,checkboxes',
            'fields.*.options'    => 'nullable|array',
            'fields.*.required'   => 'boolean',
            'fields.*.sort_order' => 'integer',
            'fields.*.active'     => 'boolean',
        ]);

        $incomingIds = collect($request->fields)->pluck('id')->filter()->all();
        ProcurementFormField::whereNotIn('id', $incomingIds)->delete();

        foreach ($request->fields as $idx => $fieldData) {
            $attributes = [
                'label'      => $fieldData['label'],
                'type'       => $fieldData['type'],
                'options'    => $fieldData['options'] ?? null,
                'required'   => $fieldData['required'] ?? false,
                'sort_order' => $fieldData['sort_order'] ?? $idx,
                'active'     => $fieldData['active'] ?? true,
            ];

            if (!empty($fieldData['id'])) {
                ProcurementFormField::where('id', $fieldData['id'])->update($attributes);
            } else {
                ProcurementFormField::create($attributes);
            }
        }

        return back()->with('success', 'Formulaire mis à jour.');
    }

    public function storeFile(Request $request)
    {
        if (!$this->canWrite()) abort(403);

        $request->validate([
            'file'        => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp',
            'description' => 'nullable|string|max:255',
        ]);

        $file     = $request->file('file');
        $path     = $file->store('procurement/files', 'public');
        $mime     = $file->getMimeType();
        $origName = $file->getClientOriginalName();
        $size     = $file->getSize();

        ProcurementFile::create([
            'original_name'       => $origName,
            'description'         => $request->input('description'),
            'file_path'           => $path,
            'file_size'           => $size,
            'mime_type'           => $mime,
            'uploaded_by_user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Fichier ajouté.');
    }

    public function destroyFile(ProcurementFile $procurementFile)
    {
        $user = Auth::user();
        if (!$user->hasRole('admin') && $procurementFile->uploaded_by_user_id !== $user->id) {
            abort(403, 'Vous ne pouvez supprimer que vos propres fichiers.');
        }

        Storage::disk('public')->delete($procurementFile->file_path);
        $procurementFile->delete();
        return back()->with('success', 'Fichier supprimé.');
    }

    public function downloadFile(ProcurementFile $procurementFile)
    {
        $this->checkAccess('read');

        $disk = Storage::disk('public');
        if (!$disk->exists($procurementFile->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return $disk->download($procurementFile->file_path, $procurementFile->original_name);
    }

    public function history(ProcurementItem $procurementItem)
    {
        if (!$this->canExport()) abort(403);

        $history = ProcurementHistory::with('user')
            ->where('procurement_item_id', $procurementItem->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($h) => [
                'id'         => $h->id,
                'action'     => $h->action,
                'user'       => $h->user?->name,
                'changes'    => $h->changes,
                'created_at' => $h->created_at->format('d/m/Y H:i'),
            ]);

        return response()->json($history);
    }
}
