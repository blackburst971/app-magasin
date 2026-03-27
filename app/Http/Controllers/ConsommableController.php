<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Consommable;
use App\Models\ConsommableMovement;
use App\Models\ConsommableRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConsommableController extends Controller
{
    // ─── Helper image ────────────────────────────────────────────────────
    private function getImageUrl(?string $image): ?string
    {
        if (!$image) return null;
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) return $image;
        return Storage::url($image);
    }

    // ─── Map Consommable → array for Inertia ─────────────────────────────
    private function mapConsommable(Consommable $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'reference'      => $c->reference,
            'unit'           => $c->unit,
            'quantity'       => $c->quantity,
            'min_stock'      => $c->min_stock,
            'category_id'    => $c->category_id,
            'category'       => $c->category?->name,
            'is_requestable' => $c->is_requestable,
            'unit_cost'      => $c->unit_cost,
            'description'    => $c->description,
            'image_url'      => $this->getImageUrl($c->image),
            'is_low_stock'   => $c->is_low_stock,
            'is_out_of_stock'=> $c->is_out_of_stock,
            'stock_value'    => $c->stock_value,
        ];
    }

    // ─── INDEX ───────────────────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $isAdmin = $request->user()->hasRole('admin');

        $query = Consommable::with('category')
            ->when(!$isAdmin, fn($q) => $q->where('is_requestable', true))
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('reference', 'like', "%{$request->search}%");
            }))
            ->when($request->category,     fn($q) => $q->where('category_id', $request->category))
            ->when($request->requestable,  fn($q) => $q->where('is_requestable', true));

        $perPage = max(12, min(200, (int) ($request->per_page ?? 24)));

        $consommables = $query->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn($c) => $this->mapConsommable($c));

        // Demandes : admin voit toutes les pending, user voit les siennes
        $requestsQuery = ConsommableRequest::with(['consommable', 'user', 'approvedBy'])
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $request->user()->id))
            ->when($request->req_status, fn($q) => $q->where('status', $request->req_status))
            ->latest();

        $consommableRequests = $requestsQuery->paginate(15, ['*'], 'requests_page')
            ->withQueryString()
            ->through(fn($r) => [
                'id'             => $r->id,
                'consommable_id' => $r->consommable_id,
                'consommable'    => $r->consommable->name,
                'reference'      => $r->consommable->reference,
                'unit'           => $r->consommable->unit,
                'user_id'        => $r->user_id,
                'user'           => $r->user->name,
                'quantity'       => $r->quantity,
                'notes'          => $r->notes,
                'status'         => $r->status,
                'status_label'   => $r->status_label,
                'status_color'   => $r->status_color,
                'admin_notes'    => $r->admin_notes,
                'approved_by'    => $r->approvedBy?->name,
                'approved_at'    => $r->approved_at?->format('d/m/Y H:i'),
                'created_at'     => $r->created_at->format('d/m/Y H:i'),
            ]);

        $categories   = Category::orderBy('name')->get(['id', 'name']);
        $pendingCount = ConsommableRequest::pending()
            ->when(!$isAdmin, fn($q) => $q->where('user_id', $request->user()->id))
            ->count();

        return Inertia::render('Consommables/Index', [
            'consommables'        => $consommables,
            'consommableRequests' => $consommableRequests,
            'categories'          => $categories,
            'pendingCount'        => $pendingCount,
            'filters'             => $request->only(['search', 'category', 'requestable', 'req_status', 'tab']),
            'isAdmin'             => $isAdmin,
        ]);
    }

    // ─── STORE ───────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'reference'      => 'required|string|max:100|unique:consommables,reference',
            'unit'           => 'required|string|max:50',
            'quantity'       => 'required|integer|min:0',
            'min_stock'      => 'required|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'is_requestable' => 'boolean',
            'unit_cost'      => 'nullable|numeric|min:0',
            'description'    => 'nullable|string|max:2000',
            'image'          => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('consommables', 'public');
        }

        $consommable = Consommable::create($data);

        // Log du mouvement initial si quantité > 0
        if ($consommable->quantity > 0) {
            ConsommableMovement::create([
                'consommable_id'  => $consommable->id,
                'old_quantity'    => 0,
                'new_quantity'    => $consommable->quantity,
                'quantity_change' => $consommable->quantity,
                'user_id'         => $request->user()->id,
                'action'          => 'add',
                'notes'           => 'Stock initial à la création',
            ]);
        }

        return back()->with('success', 'Consommable créé avec succès.');
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────
    public function update(Request $request, Consommable $consommable)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'reference'      => "required|string|max:100|unique:consommables,reference,{$consommable->id}",
            'unit'           => 'required|string|max:50',
            'quantity'       => 'required|integer|min:0',
            'min_stock'      => 'required|integer|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'is_requestable' => 'boolean',
            'unit_cost'      => 'nullable|numeric|min:0',
            'description'    => 'nullable|string|max:2000',
            'image'          => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
            'remove_image'   => 'nullable|boolean',
        ]);

        // Gestion image
        if ($request->boolean('remove_image') && $consommable->image) {
            Storage::disk('public')->delete($consommable->image);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($consommable->image) Storage::disk('public')->delete($consommable->image);
            $data['image'] = $request->file('image')->store('consommables', 'public');
        } else {
            unset($data['image']); // Conserver l'image actuelle
        }

        // Log si quantité modifiée
        $oldQty = $consommable->quantity;
        $newQty = (int) $data['quantity'];
        if ($oldQty !== $newQty) {
            ConsommableMovement::create([
                'consommable_id'  => $consommable->id,
                'old_quantity'    => $oldQty,
                'new_quantity'    => $newQty,
                'quantity_change' => $newQty - $oldQty,
                'user_id'         => $request->user()->id,
                'action'          => 'edit',
                'notes'           => 'Modification via formulaire',
            ]);
        }

        $consommable->update($data);

        return back()->with('success', 'Consommable mis à jour.');
    }

    // ─── DESTROY ─────────────────────────────────────────────────────────
    public function destroy(Consommable $consommable)
    {
        if ($consommable->image) {
            Storage::disk('public')->delete($consommable->image);
        }
        $consommable->delete();
        return back()->with('success', 'Consommable supprimé.');
    }

    // ─── ADJUST STOCK ────────────────────────────────────────────────────
    public function adjustStock(Request $request, Consommable $consommable)
    {
        $data = $request->validate([
            'delta' => 'required|integer|not_in:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldQty = $consommable->quantity;
        $newQty = $oldQty + $data['delta'];

        if ($newQty < 0) {
            return back()->withErrors(['delta' => "Impossible : le stock passerait à {$newQty}. Stock actuel : {$oldQty}."]);
        }

        $consommable->update(['quantity' => $newQty]);

        ConsommableMovement::create([
            'consommable_id'  => $consommable->id,
            'old_quantity'    => $oldQty,
            'new_quantity'    => $newQty,
            'quantity_change' => $data['delta'],
            'user_id'         => $request->user()->id,
            'action'          => $data['delta'] > 0 ? 'add' : 'remove',
            'notes'           => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Stock ajusté avec succès.');
    }

    // ─── IMPORT PREVIEW ──────────────────────────────────────────────────
    public function importPreview(Request $request)
    {
        $request->validate([
            'file'      => 'required|file|mimes:csv,txt|max:5120',
            'separator' => 'required|in:comma,semicolon',
        ]);

        $sep = $request->separator === 'semicolon' ? ';' : ',';
        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        $headers = [];
        $preview = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (empty($headers)) {
                $headers = array_map('trim', $row);
                continue;
            }
            if (count($preview) < 5) {
                $preview[] = array_combine($headers, array_pad($row, count($headers), ''));
            }
            $rowCount++;
        }
        fclose($handle);

        // Mapping automatique des colonnes
        $fieldMap = [
            'name'        => ['nom', 'name', 'désignation', 'libellé'],
            'reference'   => ['reference', 'ref', 'référence', 'code', 'sku'],
            'unit'        => ['unit', 'unité', 'unite'],
            'quantity'    => ['quantity', 'quantité', 'qte', 'stock', 'qty'],
            'min_stock'   => ['min_stock', 'stock_min', 'minimum', 'seuil'],
            'unit_cost'   => ['unit_cost', 'prix', 'price', 'coût', 'cost'],
            'category'    => ['category', 'catégorie', 'categorie'],
            'description' => ['description', 'notes'],
        ];

        $suggestedMapping = [];
        foreach ($headers as $header) {
            $lower = mb_strtolower(trim($header));
            foreach ($fieldMap as $field => $aliases) {
                if (in_array($lower, $aliases)) {
                    $suggestedMapping[$header] = $field;
                    break;
                }
            }
        }

        return response()->json([
            'headers'          => $headers,
            'preview'          => $preview,
            'row_count'        => $rowCount,
            'suggested_mapping'=> $suggestedMapping,
        ]);
    }

    // ─── IMPORT PROCESS ──────────────────────────────────────────────────
    public function importProcess(Request $request)
    {
        $request->validate([
            'file'          => 'required|file|mimes:csv,txt|max:5120',
            'separator'     => 'required|in:comma,semicolon',
            'mapping'       => 'required|array',
            'qty_mode'      => 'required|in:replace,increment',
        ]);

        $sep     = $request->separator === 'semicolon' ? ';' : ',';
        $mapping = $request->mapping; // { "CSV Column" => "field_name" }
        $qtyMode = $request->qty_mode;

        $handle  = fopen($request->file('file')->getRealPath(), 'r');
        $headers = [];
        $created = 0;
        $updated = 0;
        $errors  = [];
        $rowNum  = 0;

        while (($row = fgetcsv($handle, 0, $sep)) !== false) {
            if (empty($headers)) {
                $headers = array_map('trim', $row);
                continue;
            }
            $rowNum++;
            $raw = array_combine($headers, array_pad($row, count($headers), ''));

            // Appliquer le mapping
            $data = [];
            foreach ($mapping as $csvCol => $field) {
                if (empty($field) || !isset($raw[$csvCol])) continue;
                $val = trim($raw[$csvCol]);
                if ($val === '') continue;

                switch ($field) {
                    case 'quantity':
                    case 'min_stock':
                        $data[$field] = max(0, (int) $val);
                        break;
                    case 'unit_cost':
                        $data[$field] = (float) str_replace(',', '.', $val);
                        break;
                    case 'category':
                        $cat = Category::where('name', $val)->first();
                        if ($cat) $data['category_id'] = $cat->id;
                        break;
                    default:
                        $data[$field] = $val;
                }
            }

            // Validation minimale
            if (empty($data['reference'])) {
                $errors[] = "Ligne {$rowNum} : référence manquante.";
                continue;
            }
            if (empty($data['name'])) {
                $errors[] = "Ligne {$rowNum} : nom manquant.";
                continue;
            }

            try {
                $existing = Consommable::where('reference', $data['reference'])->first();

                if ($existing) {
                    // Mode incrément : additionner la quantité importée
                    if ($qtyMode === 'increment' && isset($data['quantity'])) {
                        $oldQty = $existing->quantity;
                        $delta  = (int) $data['quantity'];
                        $data['quantity'] = $oldQty + $delta;

                        ConsommableMovement::create([
                            'consommable_id'  => $existing->id,
                            'old_quantity'    => $oldQty,
                            'new_quantity'    => $data['quantity'],
                            'quantity_change' => $delta,
                            'user_id'         => $request->user()->id,
                            'action'          => 'import',
                            'notes'           => 'Import CSV',
                        ]);
                    } elseif (isset($data['quantity'])) {
                        // Mode remplacement : log si changement
                        $oldQty = $existing->quantity;
                        $newQty = (int) $data['quantity'];
                        if ($oldQty !== $newQty) {
                            ConsommableMovement::create([
                                'consommable_id'  => $existing->id,
                                'old_quantity'    => $oldQty,
                                'new_quantity'    => $newQty,
                                'quantity_change' => $newQty - $oldQty,
                                'user_id'         => $request->user()->id,
                                'action'          => 'import',
                                'notes'           => 'Import CSV (remplacement)',
                            ]);
                        }
                    }

                    $existing->update($data);
                    $updated++;
                } else {
                    $data = array_merge([
                        'unit'       => 'pièce',
                        'quantity'   => 0,
                        'min_stock'  => 0,
                    ], $data);

                    $consommable = Consommable::create($data);

                    if (($data['quantity'] ?? 0) > 0) {
                        ConsommableMovement::create([
                            'consommable_id'  => $consommable->id,
                            'old_quantity'    => 0,
                            'new_quantity'    => $consommable->quantity,
                            'quantity_change' => $consommable->quantity,
                            'user_id'         => $request->user()->id,
                            'action'          => 'import',
                            'notes'           => 'Import CSV (création)',
                        ]);
                    }

                    $created++;
                }
            } catch (\Exception $e) {
                $errors[] = "Ligne {$rowNum} : " . $e->getMessage();
            }
        }

        fclose($handle);

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }
}
