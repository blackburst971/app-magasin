<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Category;
use App\Models\AssetMovement;
use App\Models\EquipmentModel;
use App\Models\Manufacturer;
use App\Models\ReservationWaitlist;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    private function reservableFilterEnabled(): bool
    {
        return Setting::get('filter_by_reservable', 'true') === 'true';
    }

    private function getImageUrl(?string $image): ?string
    {
        if (!$image) return null;
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        return Storage::url($image);
    }

    public function index(Request $request): Response
    {
        $isAdmin = $request->user()->hasRole('admin');
        $reservableOn = $this->reservableFilterEnabled();

        $baseQuery = fn() => Asset::with(array_filter([
                'category', 'checkedOutTo', 'equipmentModel',
                $isAdmin ? 'accessories' : null,
            ]))
            ->when(!$isAdmin && $reservableOn, fn($q) => $q->where('is_reservable', true))
            // Les accessoires (liés à un parent) sont masqués pour les utilisateurs :
            // ils sont automatiquement inclus lors de la réservation du parent.
            ->when(!$isAdmin, fn($q) => $q->whereNull('parent_asset_id'));

        $query = $baseQuery()
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('asset_tag', 'like', "%{$request->search}%")
                   ->orWhere('serial', 'like', "%{$request->search}%");
            }))
            ->when($request->category,     fn($q) => $q->where('category_id', $request->category))
            ->when($request->status,       fn($q) => $q->where('status', $request->status))
            ->when($request->location,     fn($q) => $q->where('location', $request->location))
            // Filtrer par manufacturer_id (entité) OU par chaîne legacy (rétrocompatibilité)
            ->when($request->manufacturer, fn($q) => $q->where(function($q2) use ($request) {
                $q2->where('manufacturer_id', $request->manufacturer)
                   ->orWhere('manufacturer', $request->manufacturer);
            }));

        // Nombre d'éléments par page — min:12, max:200
        $perPage = max(12, min(200, (int) ($request->per_page ?? 24)));

        $assets = $query->orderBy('name')->paginate($perPage, ['*'], 'page', (int)($request->page ?? 1))->withQueryString()
            ->through(fn($a) => [
                'id'                    => $a->id,
                'name'                  => $a->name,
                'asset_tag'             => $a->asset_tag,
                'serial'                => $a->serial,
                'status'                => $a->status,
                'status_label'          => $a->status_label,
                'status_color'          => $a->status_color,
                'is_reservable'         => $a->is_reservable,
                'category'              => $a->category?->name,
                'category_id'           => $a->category_id,
                'location'              => $a->location,
                'manufacturer'          => $a->manufacturer,
                'image'                 => $this->getImageUrl($a->image),
                'checked_out_to'        => $a->checkedOutTo ? ['name' => $a->checkedOutTo->name] : null,
                'expected_return_at'    => $a->expected_return_at?->format('d/m/Y'),
                'equipment_model_id'    => $a->equipment_model_id,
                'model_name'            => $a->equipmentModel?->name,
                'accessories'           => $isAdmin
                    ? $a->accessories->map(fn($acc) => ['id' => $acc->id, 'name' => $acc->name])->values()
                    : [],
            ]);

        $categories = Category::withCount(['assets' => fn($q) =>
            $q->when(!$isAdmin && $reservableOn, fn($q2) => $q2->where('is_reservable', true))
        ])->orderBy('name')->get(['id', 'name', 'description', 'image', 'color'])
          ->map(fn($c) => [
              'id'          => $c->id,
              'name'        => $c->name,
              'description' => $c->description,
              'image_url'   => $this->getImageUrl($c->image),
              'color'       => $c->color,
              'assets_count'=> $c->assets_count,
          ]);

        // Données pour les onglets de navigation
        $locations = $baseQuery()
            ->whereNotNull('location')->where('location', '!=', '')
            ->groupBy('location')
            ->selectRaw('location, count(*) as asset_count')
            ->orderBy('location')
            ->get();

        // Fabricants : entités Manufacturer (plus de distinct string)
        // Note: on filtre en PHP plutôt qu'avec HAVING pour compatibilité SQLite
        $manufacturers = Manufacturer::withCount(['assets' => fn($q) =>
            $q->when(!$isAdmin && $reservableOn, fn($q2) => $q2->where('is_reservable', true))
        ])->orderBy('name')->get(['id', 'name'])
          ->filter(fn($m) => $m->assets_count > 0)
          ->values();

        $models = EquipmentModel::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Assets/Index', [
            'assets'                 => $assets,
            'categories'             => $categories,
            'locations'              => $locations,
            'manufacturers'          => $manufacturers,
            'models'                 => $models,
            'filters'                => $request->only(['search', 'category', 'status', 'location', 'manufacturer', 'tab']),
            'reservableFilterActive' => $reservableOn,
        ]);
    }

    public function show(Asset $asset): Response
    {
        $isAdmin = request()->user()->hasRole('admin');
        if (!$isAdmin && !$asset->is_reservable && $this->reservableFilterEnabled()) {
            abort(403);
        }

        $asset->load(['category', 'movements.user', 'equipmentModel.documents', 'supplier', 'maintenances.createdBy', 'accessories', 'parentAsset', 'files']);

        $movements = $asset->movements->map(fn($m) => [
            'id'         => $m->id,
            'action'     => $m->action,
            'user'       => $m->user ? ['name' => $m->user->name] : null,
            'notes'      => $m->notes,
            'created_at' => $m->created_at->format('d/m/Y H:i'),
        ]);

        $activeReservation = $asset->reservations()
            ->whereIn('reservations.status', ['active', 'approved'])
            ->with('user')
            ->first();

        // Documents PDF du modèle lié
        $modelDocuments = $asset->equipmentModel
            ? $asset->equipmentModel->documents->map(fn($d) => [
                'id'             => $d->id,
                'name'           => $d->name,
                'original_name'  => $d->original_name,
                'url'            => $d->url,
                'formatted_size' => $d->formatted_size,
              ])
            : collect();

        $user = request()->user();
        $isOnWaitlist = ReservationWaitlist::where('asset_id', $asset->id)
            ->where('user_id', $user->id)
            ->exists();

        $maintenances = $asset->maintenances->map(fn($m) => [
            'id'                 => $m->id,
            'title'              => $m->title,
            'status'             => $m->status,
            'status_label'       => $m->status_label,
            'started_at'         => $m->started_at?->format('d/m/Y'),
            'expected_return_at' => $m->expected_return_at?->format('d/m/Y'),
            'resolved_at'        => $m->resolved_at?->format('d/m/Y'),
            'provider'           => $m->provider,
            'cost'               => $m->cost,
            'created_by_name'    => $m->createdBy?->name ?? 'N/A',
        ])->sortByDesc('id')->values();

        $accessories = $asset->accessories->map(fn($a) => [
            'id'           => $a->id,
            'name'         => $a->name,
            'asset_tag'    => $a->asset_tag,
            'status'       => $a->status,
            'status_label' => $a->status_label,
            'image_url'    => $this->getImageUrl($a->image),
        ])->values();

        // Équipements disponibles pour lier comme accessoire (même pas de parent, pas déjà cet asset)
        $linkableAssets = Asset::where('id', '!=', $asset->id)
            ->whereNull('parent_asset_id')
            ->whereNotIn('id', $asset->accessories->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'status'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'asset_tag' => $a->asset_tag])
            ->values();

        // Fichiers publics attachés (images + PDFs)
        $assetFiles = $asset->files->map(fn($f) => [
            'id'             => $f->id,
            'name'           => $f->name,
            'url'            => Storage::url($f->path),
            'type'           => $f->type,
            'mime_type'      => $f->mime_type,
            'formatted_size' => $f->formatted_size,
            'is_image'       => $f->is_image,
            'is_pdf'         => $f->is_pdf,
        ])->values();

        return Inertia::render('Assets/Show', [
            'asset'             => array_merge($asset->toArray(), [
                'status_label'       => $asset->status_label,
                'image_url'          => $this->getImageUrl($asset->image),
                'qr_code_url'        => $asset->qr_code ? Storage::url($asset->qr_code) : null,
                'model'              => $asset->equipmentModel?->name,
                'supplier'           => $asset->supplier ? ['name' => $asset->supplier->name] : null,
                'parent_asset'       => $asset->parentAsset ? ['id' => $asset->parentAsset->id, 'name' => $asset->parentAsset->name] : null,
                'public_description' => $asset->public_description,
                'custom_fields'      => $asset->custom_fields ?? [],
            ]),
            'accessories'       => $accessories,
            'assetFiles'        => $assetFiles,
            'linkableAssets'    => $linkableAssets,
            'movements'         => $movements,
            'modelDocuments'    => $modelDocuments,
            'maintenances'      => $maintenances,
            'isOnWaitlist'      => $isOnWaitlist,
            'activeReservation' => $activeReservation ? [
                'id'         => $activeReservation->id,
                'title'      => $activeReservation->title,
                'start_date' => $activeReservation->start_date,
                'end_date'   => $activeReservation->end_date,
                'user'       => ['name' => $activeReservation->user?->name],
            ] : null,
        ]);
    }

    /** Mettre à jour la description publique et les champs personnalisés */
    public function updatePublicInfo(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'public_description'         => 'nullable|string|max:10000',
            'custom_fields'              => 'nullable|array|max:20',
            'custom_fields.*.key'        => 'required|string|max:100',
            'custom_fields.*.value'      => 'required|string|max:1000',
        ]);

        $asset->update([
            'public_description' => $data['public_description'] ?? null,
            'custom_fields'      => !empty($data['custom_fields'])
                ? array_values(array_filter($data['custom_fields'], fn($f) => !empty($f['key']) && !empty($f['value'])))
                : null,
        ]);

        return back()->with('success', 'Fiche publique mise à jour.');
    }

    public function create(): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return Inertia::render('Assets/Form', ['categories' => $categories]);
    }

    /** Retourne les clés de statut valides depuis la DB (mise en cache) */
    private function validStatusKeys(): string
    {
        return \App\Models\AssetStatus::allCached()->pluck('key')->implode(',');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_tag'     => 'required|string|max:100|unique:assets',
            'serial'        => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'manufacturer'  => 'nullable|string|max:255',
            'category_id'   => 'nullable|exists:categories,id',
            'location'      => 'nullable|string|max:255',
            'status'        => 'required|in:' . $this->validStatusKeys(),
            'is_reservable' => 'boolean',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'image'         => 'nullable|image|max:2048',
        ]);

        $data['is_reservable'] = $request->boolean('is_reservable', true);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('assets', 'public');
        }

        $asset = Asset::create($data);
        $this->generateQrCode($asset);

        AssetMovement::create([
            'asset_id' => $asset->id,
            'user_id'  => $request->user()->id,
            'action'   => 'import',
            'notes'    => 'Création du matériel',
        ]);

        return redirect()->route('assets.index')->with('success', 'Équipement créé avec succès.');
    }

    public function edit(Asset $asset): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return Inertia::render('Assets/Form', [
            'asset'      => array_merge($asset->toArray(), [
                'image_url' => $this->getImageUrl($asset->image),
            ]),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'asset_tag'     => "required|string|max:100|unique:assets,asset_tag,{$asset->id}",
            'serial'        => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'manufacturer'  => 'nullable|string|max:255',
            'category_id'   => 'nullable|exists:categories,id',
            'location'      => 'nullable|string|max:255',
            'status'        => 'required|in:' . $this->validStatusKeys(),
            'is_reservable' => 'boolean',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
            'image'         => 'nullable|image|max:2048',
        ]);

        $data['is_reservable'] = $request->boolean('is_reservable', $asset->is_reservable);

        if ($request->boolean('remove_image')) {
            if ($asset->image && !str_starts_with($asset->image, 'http')) {
                Storage::disk('public')->delete($asset->image);
            }
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($asset->image && !str_starts_with($asset->image, 'http')) {
                Storage::disk('public')->delete($asset->image);
            }
            $data['image'] = $request->file('image')->store('assets', 'public');
        } else {
            unset($data['image']); // conserver l'image existante
        }

        $oldStatus = $asset->status;
        $asset->update($data);

        if ($oldStatus !== $asset->status) {
            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id'  => $request->user()->id,
                'action'   => 'status_change',
                'notes'    => "Statut changé de {$oldStatus} à {$asset->status}",
            ]);
        }

        return redirect()->route('assets.show', $asset)->with('success', 'Équipement mis à jour.');
    }

    /** Toggle rapide is_reservable — appel PATCH via axios (admin only) */
    public function toggleReservable(Asset $asset)
    {
        $asset->update(['is_reservable' => !$asset->is_reservable]);

        return response()->json(['is_reservable' => $asset->is_reservable]);
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();
        return redirect()->route('assets.index')->with('success', 'Équipement supprimé.');
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:assets,id',
        ]);

        $count = Asset::whereIn('id', $request->ids)->delete();

        return back()->with('success', "{$count} équipement(s) supprimé(s) définitivement.");
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'ids'                  => 'required|array|min:1',
            'ids.*'                => 'integer|exists:assets,id',
            'status'               => 'nullable|string',
            'category_id'          => 'nullable|integer|exists:categories,id',
            'location'             => 'nullable|string|max:255',
            'is_reservable'        => 'nullable|in:0,1',
            'equipment_model_id'   => 'nullable|integer|exists:equipment_models,id',
        ]);

        $updates = [];
        if ($request->has('status') && $request->status !== '')
            $updates['status'] = $request->status;
        if ($request->has('category_id'))
            $updates['category_id'] = $request->category_id ?: null;
        if ($request->has('location'))
            $updates['location'] = $request->location ?: null;
        if ($request->has('is_reservable') && $request->is_reservable !== '')
            $updates['is_reservable'] = (bool) $request->is_reservable;
        if ($request->has('equipment_model_id'))
            $updates['equipment_model_id'] = $request->equipment_model_id ?: null;

        if (!empty($updates)) {
            Asset::whereIn('id', $request->ids)->update($updates);
        }

        $count = count($request->ids);
        return back()->with('success', "{$count} équipement(s) mis à jour.");
    }

    public function duplicate(Asset $asset)
    {
        // Générer un tag unique
        $attempt = 0;
        do {
            $tag = 'EQ-' . strtoupper(substr(str_pad(base_convert(mt_rand(0, 1679615), 10, 36), 5, '0', STR_PAD_LEFT), 0, 5));
            $attempt++;
        } while (Asset::where('asset_tag', $tag)->exists() && $attempt < 20);

        $newAsset = Asset::create([
            'name'               => $asset->name . ' (copie)',
            'asset_tag'          => $tag,
            'serial'             => null,
            'model'              => $asset->model,
            'manufacturer'       => $asset->manufacturer,
            'location'           => $asset->location,
            'status'             => 'available',
            'is_reservable'      => $asset->is_reservable,
            'category_id'        => $asset->category_id,
            'equipment_model_id' => $asset->equipment_model_id,
            'notes'              => $asset->notes,
            'image'              => $asset->image,
            'purchase_date'      => $asset->purchase_date,
            'purchase_cost'      => $asset->purchase_cost,
        ]);

        $this->generateQrCode($newAsset);

        AssetMovement::create([
            'asset_id' => $newAsset->id,
            'user_id'  => request()->user()->id,
            'action'   => 'import',
            'notes'    => "Dupliqué depuis « {$asset->name} » (#{$asset->id})",
        ]);

        return back()->with('success', "Équipement « {$asset->name} » dupliqué (tag : {$tag}).");
    }

    public function available(Request $request)
    {
        $start = $request->start;
        $end   = $request->end;

        // Récupérer les statuts qui bloquent les réservations (dynamique, depuis la DB)
        $blockedStatuses = \App\Models\AssetStatus::allCached()
            ->where('blocks_reservation', true)
            ->pluck('key')
            ->toArray();

        $isAdmin = $request->user()->hasRole('admin');

        $query = Asset::with('category')
            ->whereNotIn('status', $blockedStatuses)
            ->where('is_reservable', true)
            // Les accessoires ne sont pas réservables directement par les utilisateurs
            ->when(!$isAdmin, fn($q) => $q->whereNull('parent_asset_id'));

        $assets = $query->get()->map(function ($a) use ($start, $end) {
            $availability = null;
            if ($start && $end) {
                $availability = $a->getAvailabilityForPeriod(
                    new \DateTime($start),
                    new \DateTime($end)
                );
            }
            return [
                'id'           => $a->id,
                'name'         => $a->name,
                'asset_tag'    => $a->asset_tag,
                'category'     => $a->category?->name,
                'status'       => $a->status,
                'status_label' => $a->status_label,
                'availability' => $availability,
                'image'        => $this->getImageUrl($a->image),
            ];
        })->values();

        return response()->json($assets);
    }

    public function qrcode(Asset $asset)
    {
        $qr = QrCode::format('svg')->size(300)->generate(url('/assets/' . $asset->id));
        return response($qr)->header('Content-Type', 'image/svg+xml');
    }

    public function generateQr(Asset $asset)
    {
        $this->generateQrCode($asset);
        return back()->with('success', 'QR code généré.');
    }

    private function generateQrCode(Asset $asset): void
    {
        $path = "qrcodes/asset_{$asset->id}.svg";
        $qr   = QrCode::format('svg')->size(200)->generate(url('/assets/' . $asset->id));
        Storage::disk('public')->put($path, $qr);
        $asset->update(['qr_code' => $path]);
    }

    /**
     * Lier un accessoire à cet équipement (parent → enfant).
     */
    public function linkAccessory(Request $request, Asset $asset)
    {
        $request->validate(['accessory_id' => 'required|exists:assets,id']);

        $accessory = Asset::findOrFail($request->accessory_id);

        if ($accessory->id === $asset->id) {
            return back()->withErrors(['accessory_id' => 'Un équipement ne peut pas être son propre accessoire.']);
        }

        if ($accessory->parent_asset_id) {
            return back()->withErrors(['accessory_id' => 'Cet équipement est déjà rattaché à un autre parent.']);
        }

        $accessory->update(['parent_asset_id' => $asset->id]);

        return back()->with('success', "{$accessory->name} rattaché comme accessoire.");
    }

    /**
     * Détacher un accessoire de son parent.
     */
    public function unlinkAccessory(Request $request, Asset $asset)
    {
        $request->validate(['accessory_id' => 'required|exists:assets,id']);

        Asset::where('id', $request->accessory_id)
             ->where('parent_asset_id', $asset->id)
             ->update(['parent_asset_id' => null]);

        return back()->with('success', 'Accessoire détaché.');
    }
}
