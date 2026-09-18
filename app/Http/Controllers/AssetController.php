<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\AssetPlaceAssignment;
use App\Models\Category;
use App\Models\AssetMovement;
use App\Models\EquipmentModel;
use App\Models\Manufacturer;
use App\Models\Place;
use App\Models\ReservationWaitlist;
use App\Models\Setting;
use App\Models\TodoProject;
use App\Models\TodoTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

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

        // Statuts dont les équipements sont masqués aux utilisateurs
        $hiddenStatusKeys = !$isAdmin
            ? \App\Models\AssetStatus::allCached()->where('hide_from_users', true)->pluck('key')->toArray()
            : [];

        $baseQuery = fn() => Asset::with(array_filter([
                'category', 'checkedOutTo', 'equipmentModel',
                $isAdmin ? 'accessories' : null,
            ]))
            ->when(!$isAdmin && $reservableOn, fn($q) => $q->where('is_reservable', true))
            // Les accessoires (liés à un parent) sont masqués pour les utilisateurs :
            // ils sont automatiquement inclus lors de la réservation du parent.
            ->when(!$isAdmin, fn($q) => $q->whereNull('parent_asset_id'))
            // Masquer les assets dont le statut est configuré comme invisible pour les utilisateurs
            ->when(!$isAdmin && !empty($hiddenStatusKeys), fn($q) => $q->whereNotIn('status', $hiddenStatusKeys));

        $query = $baseQuery()
            ->when($request->search, fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('asset_tag', 'like', "%{$request->search}%")
                   ->orWhere('serial', 'like', "%{$request->search}%");
            }))
            ->when($request->category,     fn($q) => $q->where('category_id', $request->category))
            ->when($request->status,       fn($q) => $q->where('status', $request->status))
            ->when($request->location,     fn($q) => $q->where('location', $request->location))
            ->when($request->place,        fn($q) => $q->where('place_id', $request->place))
            // Filtrer par manufacturer_id (entité) OU par chaîne legacy (rétrocompatibilité)
            ->when($request->manufacturer, fn($q) => $q->where(function($q2) use ($request) {
                $q2->where('manufacturer_id', $request->manufacturer)
                   ->orWhere('manufacturer', $request->manufacturer);
            }))
            ->when($request->model, fn($q) => $q->where('equipment_model_id', $request->model));

        // Nombre d'éléments par page — min:12, max:500
        $perPage = max(12, min(500, (int) ($request->per_page ?? 24)));

        $assets = $query->with(['accessories.category', 'parentAsset'])->orderBy('name')->paginate($perPage, ['*'], 'page', (int)($request->page ?? 1))->withQueryString()
            ->through(function ($a) use ($isAdmin) {
                $statusObj = \App\Models\AssetStatus::allCached()->firstWhere('key', $a->status);

                // Préparer les accessoires : si l'équipement a des accessoires (enfants), les afficher
                // Sinon, si c'est un accessoire lui-même (a un parent), afficher le parent
                $accessoriesData = [];
                if ($a->accessories->isNotEmpty()) {
                    // Cet équipement a des accessoires rattachés - inclure toutes les données pour le panier
                    $accessoriesData = $a->accessories->map(fn($acc) => [
                        'id'        => $acc->id,
                        'name'      => $acc->name,
                        'asset_tag' => $acc->asset_tag,
                        'category'  => $acc->category?->name,
                        'status'    => $acc->status,
                        'image'     => $this->getImageUrl($acc->image),
                    ])->values()->toArray();
                } elseif ($a->parentAsset) {
                    // Cet équipement est un accessoire d'un autre
                    $accessoriesData = [['id' => $a->parentAsset->id, 'name' => '↑ ' . $a->parentAsset->name]];
                }

                return [
                    'id'                    => $a->id,
                    'name'                  => $a->name,
                    'asset_tag'             => $a->asset_tag,
                    'serial'                => $a->serial,
                    'status'                => $a->status,
                    'status_label'          => $a->status_label,
                    'status_color'          => $a->status_color,
                    'status_blocks_reservation' => $statusObj ? (bool) $statusObj->blocks_reservation : false,
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
                    'accessories'           => $accessoriesData,
                    'condition_note'        => $a->condition_note,
                    'label_to_create'       => $isAdmin ? (bool) $a->label_to_create : null,
                    'label_to_apply'        => $isAdmin ? (bool) $a->label_to_apply  : null,
                ];
            });

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
            ->get()
            ->pluck('location')
            ->values()
            ->toArray();

        // Lieux formels (places) pour le tab Lieux — source unique partagée avec Attribution
        $places = \App\Models\Place::withCount('activeAssignments')
            ->orderBy('is_storage', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'color', 'is_storage'])
            ->map(fn($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'description'      => $p->description,
                'color'            => $p->color,
                'is_storage'       => $p->is_storage,
                'active_assignments_count' => $p->active_assignments_count,
            ]);

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
            'places'                 => $places,
            'manufacturers'          => $manufacturers,
            'models'                 => $models,
            'filters'                => $request->only(['search', 'category', 'status', 'location', 'place', 'manufacturer', 'tab', 'model']),
            'reservableFilterActive' => $reservableOn,
        ]);
    }

    public function show(Asset $asset): Response
    {
        $isAdmin = request()->user()->hasRole('admin');
        if (!$isAdmin && !$asset->is_reservable && $this->reservableFilterEnabled()) {
            abort(403);
        }
        // Bloquer l'accès direct aux assets dont le statut est masqué aux utilisateurs
        if (!$isAdmin) {
            $statusObj = \App\Models\AssetStatus::allCached()->firstWhere('key', $asset->status);
            if ($statusObj && $statusObj->hide_from_users) {
                abort(403);
            }
        }

        $asset->load(['category', 'movements.user', 'equipmentModel.documents', 'supplier', 'maintenances.createdBy', 'accessories', 'parentAsset', 'files', 'activeAssignment.assignedToAsset', 'assignedAssets.asset', 'components', 'reports.user', 'reports.validatedBy']);

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

        // Affectation active de cet équipement vers un autre
        $activeAssignment = $asset->activeAssignment;

        // Équipements affectés à cet asset (en tant que destinataire)
        $assignedAssets = $asset->assignedAssets->map(fn($asgn) => [
            'id'             => $asgn->id,
            'asset_id'       => $asgn->asset_id,
            'asset_name'     => $asgn->asset->name,
            'asset_tag'      => $asgn->asset->asset_tag,
            'notes'          => $asgn->notes,
            'assigned_at'    => $asgn->assigned_at->format('d/m/Y'),
            'assigned_by'    => $asgn->assignedBy?->name,
        ])->values();

        // Équipements disponibles pour être affectés (disponibles, pas d'affectation active)
        $assignableAssets = $isAdmin ? Asset::where('id', '!=', $asset->id)
            ->whereIn('status', ['ready_to_deploy', 'available'])
            ->whereDoesntHave('assetAssignments', fn($q) => $q->whereNull('returned_at'))
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'status'])
            ->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'asset_tag' => $a->asset_tag])
            ->values() : collect();

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
                'model'              => $asset->equipmentModel ? ['id' => $asset->equipmentModel->id, 'name' => $asset->equipmentModel->name] : null,
                'supplier'           => $asset->supplier ? ['name' => $asset->supplier->name] : null,
                'parent_asset'       => $asset->parentAsset ? ['id' => $asset->parentAsset->id, 'name' => $asset->parentAsset->name] : null,
                'public_description' => $asset->public_description,
                'custom_fields'      => $asset->custom_fields ?? [],
                'condition_note'     => $asset->condition_note,
            ]),
            'accessories'       => $accessories,
            'assetFiles'        => $assetFiles,
            'linkableAssets'    => $linkableAssets,
            'activeAssignment'  => $activeAssignment ? [
                'id'               => $activeAssignment->id,
                'assigned_to_name' => $activeAssignment->assignedToAsset->name,
                'assigned_to_id'   => $activeAssignment->assigned_to_asset_id,
                'notes'            => $activeAssignment->notes,
                'assigned_at'      => $activeAssignment->assigned_at->format('d/m/Y'),
            ] : null,
            'assignedAssets'    => $assignedAssets,
            'assignableAssets'  => $assignableAssets,
            'movements'         => $movements,
            'modelDocuments'    => $modelDocuments,
            'maintenances'      => $maintenances,
            'isOnWaitlist'      => $isOnWaitlist,
            'components'        => $asset->components->map(fn($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'quantity'    => $c->quantity,
                'sort_order'  => $c->sort_order,
                'condition'   => $c->condition,
            ])->values(),
            'activeReservation' => $activeReservation ? [
                'id'         => $activeReservation->id,
                'title'      => $activeReservation->title,
                'start_date' => $activeReservation->start_date,
                'end_date'   => $activeReservation->end_date,
                'user'       => ['name' => $activeReservation->user?->name],
            ] : null,
            'reports' => $asset->reports
                ->when(!$isAdmin, fn($col) => $col->where('is_validated', true))
                ->map(fn($r) => [
                    'id'           => $r->id,
                    'type'         => $r->type,
                    'type_label'   => $r->getTypeLabel(),
                    'title'        => $r->title,
                    'content'      => $r->content,
                    'is_validated' => $r->is_validated,
                    'admin_notes'  => $r->admin_notes,
                    'validated_at' => $r->validated_at?->format('d/m/Y H:i'),
                    'validated_by' => $r->validatedBy ? ['name' => $r->validatedBy->name] : null,
                    'created_at'   => $r->created_at->format('d/m/Y H:i'),
                    'user'         => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
                    'reservation_id' => $r->reservation_id,
                ])->values(),
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

    /** Mettre à jour la note d'état de l'équipement (visible publiquement) */
    public function updateConditionNote(Request $request, Asset $asset)
    {
        $request->validate(['condition_note' => 'nullable|string|max:2000']);
        $asset->update(['condition_note' => $request->condition_note ?: null]);
        return response()->json(['ok' => true]);
    }

    public function create(): Response
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $places = \App\Models\Place::orderBy('is_storage', 'desc')->orderBy('name')->get(['id', 'name', 'color', 'is_storage']);
        $manufacturers = Manufacturer::orderBy('name')->get(['id', 'name']);
        return Inertia::render('Assets/Form', [
            'categories'    => $categories,
            'places'        => $places,
            'manufacturers' => $manufacturers,
        ]);
    }

    /** Retourne les clés de statut valides depuis la DB (mise en cache) */
    private function validStatusKeys(): string
    {
        return \App\Models\AssetStatus::allCached()->pluck('key')->implode(',');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'asset_tag'       => 'required|string|max:100|unique:assets',
            'serial'          => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'manufacturer'    => 'nullable|string|max:255',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'category_id'     => 'nullable|exists:categories,id',
            'place_id'        => 'nullable|exists:places,id',
            'status'          => 'required|in:' . $this->validStatusKeys(),
            'is_reservable'   => 'boolean',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'image'           => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ]);

        $data['is_reservable'] = $request->boolean('is_reservable', true);

        // Synchroniser location (texte) depuis le lieu sélectionné
        if (!empty($data['place_id'])) {
            $place = \App\Models\Place::find($data['place_id']);
            $data['location'] = $place?->name;
        } else {
            $data['location'] = null;
        }

        // Synchroniser manufacturer (texte) depuis manufacturer_id si sélectionné
        if (!empty($data['manufacturer_id'])) {
            $manufacturer = Manufacturer::find($data['manufacturer_id']);
            $data['manufacturer'] = $manufacturer?->name;
        }

        // Normaliser les chaînes vides en null pour les champs nullable
        foreach (['category_id', 'manufacturer', 'manufacturer_id', 'serial', 'model', 'location', 'notes', 'purchase_date', 'purchase_cost', 'image'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

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
        $places = \App\Models\Place::orderBy('is_storage', 'desc')->orderBy('name')->get(['id', 'name', 'color', 'is_storage']);
        $manufacturers = Manufacturer::orderBy('name')->get(['id', 'name']);

        $asset->load('equipmentModel.category');

        return Inertia::render('Assets/Form', [
            'asset' => array_merge($asset->toArray(), [
                'image_url'       => $this->getImageUrl($asset->image),
                'equipment_model' => $asset->equipmentModel ? [
                    'id'            => $asset->equipmentModel->id,
                    'name'          => $asset->equipmentModel->name,
                    'manufacturer'  => $asset->equipmentModel->manufacturer,
                    'category_id'   => $asset->equipmentModel->category_id,
                    'category_name' => $asset->equipmentModel->category?->name,
                ] : null,
            ]),
            'categories'    => $categories,
            'places'        => $places,
            'manufacturers' => $manufacturers,
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'asset_tag'       => "required|string|max:100|unique:assets,asset_tag,{$asset->id}",
            'serial'          => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'manufacturer'    => 'nullable|string|max:255',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'category_id'     => 'nullable|exists:categories,id',
            'place_id'        => 'nullable|exists:places,id',
            'status'          => 'required|in:' . $this->validStatusKeys(),
            'is_reservable'   => 'boolean',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'image'           => 'nullable|mimes:jpeg,jpg,png,gif,webp,avif|max:2048',
        ]);

        $data['is_reservable'] = $request->boolean('is_reservable', $asset->is_reservable);

        // Synchroniser location (texte) depuis le lieu sélectionné
        if (!empty($data['place_id'])) {
            $place = \App\Models\Place::find($data['place_id']);
            $data['location'] = $place?->name;
        } else {
            $data['location'] = null;
        }

        // Synchroniser manufacturer (texte) depuis manufacturer_id si sélectionné
        if (!empty($data['manufacturer_id'])) {
            $manufacturer = Manufacturer::find($data['manufacturer_id']);
            $data['manufacturer'] = $manufacturer?->name;
        }

        // Normaliser les chaînes vides en null pour les champs nullable
        foreach (['category_id', 'manufacturer', 'manufacturer_id', 'serial', 'model', 'location', 'notes', 'purchase_date', 'purchase_cost'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

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

        // Si l'asset est lié à un modèle, les champs hérités ne doivent pas être modifiés ici
        if ($asset->equipment_model_id) {
            unset($data['manufacturer'], $data['manufacturer_id'], $data['category_id'], $data['model'], $data['purchase_date'], $data['purchase_cost']);
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

    /**
     * Mise à jour rapide d'un champ depuis la liste (admin only).
     * Permet de modifier status, category_id, place_id, manufacturer_id, equipment_model_id
     */
    public function quickUpdate(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'field'    => 'required|in:status,category_id,location,manufacturer_id,equipment_model_id',
            'value'    => 'nullable|string|max:255',
        ]);

        $field = $data['field'];
        $value = $data['value'];

        // Validation spécifique par champ
        if ($field === 'status') {
            $validStatuses = \App\Models\AssetStatus::allCached()->pluck('key')->toArray();
            if (!in_array($value, $validStatuses)) {
                return response()->json(['error' => 'Statut invalide'], 422);
            }

            // Enregistrer le mouvement de changement de statut
            $oldStatus = $asset->status;
            $asset->update(['status' => $value]);

            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id'  => $request->user()->id,
                'action'   => 'status_change',
                'notes'    => "Statut changé de {$oldStatus} à {$value} (mise à jour rapide)",
            ]);
        } elseif ($field === 'category_id') {
            if ($value && !Category::find($value)) {
                return response()->json(['error' => 'Catégorie invalide'], 422);
            }
            $asset->update(['category_id' => $value ?: null]);
        } elseif ($field === 'location') {
            // Mise à jour directe du champ location (texte libre)
            $asset->update(['location' => $value ?: null]);
        } elseif ($field === 'manufacturer_id') {
            if ($value && !Manufacturer::find($value)) {
                return response()->json(['error' => 'Fabricant invalide'], 422);
            }
            $manufacturer = $value ? Manufacturer::find($value) : null;
            $asset->update([
                'manufacturer_id' => $value ?: null,
                'manufacturer'    => $manufacturer?->name,
            ]);
        } elseif ($field === 'equipment_model_id') {
            if ($value && !EquipmentModel::find($value)) {
                return response()->json(['error' => 'Modèle invalide'], 422);
            }
            $asset->update(['equipment_model_id' => $value ?: null]);
        }

        $asset->refresh();

        // Retourner les données mises à jour
        return response()->json([
            'success' => true,
            'asset'   => [
                'id'                 => $asset->id,
                'status'             => $asset->status,
                'status_label'       => $asset->status_label,
                'category_id'        => $asset->category_id,
                'category_name'      => $asset->category?->name,
                'location'           => $asset->location,
                'manufacturer_id'    => $asset->manufacturer_id,
                'manufacturer_name'  => $asset->manufacturerModel?->name ?? $asset->manufacturer,
                'equipment_model_id' => $asset->equipment_model_id,
                'model_name'         => $asset->equipmentModel?->name,
            ],
        ]);
    }

    /** Toggle rapide is_reservable — appel PATCH via axios (admin only) */
    public function toggleReservable(Asset $asset)
    {
        $asset->update(['is_reservable' => !$asset->is_reservable]);

        return response()->json(['is_reservable' => $asset->is_reservable]);
    }

    /** Toggle has_scratch (velcro/scratch de câblage) — admin only */
    public function toggleScratch(Asset $asset)
    {
        $asset->update(['has_scratch' => !$asset->has_scratch]);
        return response()->json(['has_scratch' => $asset->has_scratch]);
    }

    /** Toggle has_belge (sacoche/housse de transport) — admin only */
    public function toggleBelge(Asset $asset)
    {
        $asset->update(['has_belge' => !$asset->has_belge]);
        return response()->json(['has_belge' => $asset->has_belge]);
    }

    /** Toggle label_to_create — admin only */
    /**
     * Marque comme "done" toutes les tâches Todo liées à cet asset
     * si les deux flags d'étiquette sont maintenant à false.
     * Retourne le nombre de tâches complétées automatiquement.
     */
    private function autoCompleteLabelTasks(Asset $asset): int
    {
        if ($asset->label_to_create || $asset->label_to_apply) {
            return 0;
        }

        return TodoTask::whereHas('assets', fn($q) => $q->where('assets.id', $asset->id))
            ->where('status', '!=', 'done')
            ->update(['status' => 'done']);
    }

    /**
     * Scan d'une étiquette déjà créée mais pas encore collée :
     * marque l'asset comme "à coller" et crée une tâche Todo liée.
     */
    public function labelScanApply(Request $request)
    {
        $request->validate(['asset_tag' => 'required|string|max:100']);

        $asset = Asset::with('category')->where('asset_tag', $request->asset_tag)->first();

        if (!$asset) {
            return response()->json([
                'error' => "Équipement « {$request->asset_tag} » introuvable dans l'inventaire.",
            ], 404);
        }

        if ($asset->label_to_apply) {
            return response()->json([
                'warning' => "Cet équipement est déjà dans la liste des étiquettes à coller.",
                'asset'   => $this->formatLabelAsset($asset),
            ], 409);
        }

        // Marquer l'étiquette comme à coller (déjà créée)
        $asset->update([
            'label_to_apply'   => true,
            'label_flagged_at' => $asset->label_flagged_at ?? now(),
        ]);

        // Trouver ou créer le projet "Étiquettage" partagé
        $project = TodoProject::firstOrCreate(
            ['name' => 'Étiquettage'],
            [
                'description' => 'Tâches d\'étiquettage générées automatiquement',
                'color'       => '#F59E0B',
                'is_shared'   => true,
                'created_by'  => $request->user()->id,
            ]
        );

        // Créer la tâche Todo
        $task = TodoTask::create([
            'project_id'  => $project->id,
            'title'       => "Coller l'étiquette sur {$asset->name}",
            'status'      => 'todo',
            'priority'    => 'medium',
            'created_by'  => $request->user()->id,
        ]);
        $task->assets()->attach($asset->id);

        return response()->json([
            'asset' => $this->formatLabelAsset($asset),
            'task'  => ['id' => $task->id, 'title' => $task->title],
        ], 201);
    }

    /** Formate un asset pour la réponse JSON de la page Étiquettes */
    private function formatLabelAsset(Asset $asset): array
    {
        return [
            'id'                     => $asset->id,
            'name'                   => $asset->name,
            'asset_tag'              => $asset->asset_tag,
            'category'               => $asset->category?->name,
            'location'               => $asset->location,
            'image'                  => $this->getImageUrl($asset->image),
            'label_to_create'        => (bool) $asset->label_to_create,
            'label_to_apply'         => (bool) $asset->label_to_apply,
            'label_flagged_at'       => $asset->label_flagged_at?->toIso8601String(),
            'label_flagged_at_label' => $asset->label_flagged_at?->format('d/m/Y H:i'),
            'label_sort_order'       => $asset->label_sort_order,
        ];
    }

    /**
     * Enregistre l'ordre personnalisé de la liste Étiquettes — admin only.
     * Reçoit un tableau ordonné d'IDs : [id1, id2, id3, …]
     */
    public function labelsReorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:assets,id',
        ]);

        foreach ($request->ids as $position => $id) {
            Asset::where('id', $id)->update(['label_sort_order' => $position + 1]);
        }

        return response()->json(['ok' => true]);
    }

    public function toggleLabelCreate(Asset $asset)
    {
        $newValue = !$asset->label_to_create;
        $updates  = ['label_to_create' => $newValue];

        // label_flagged_at
        if ($newValue && !$asset->label_flagged_at) {
            $updates['label_flagged_at'] = now();
        } elseif (!$newValue && !$asset->label_to_apply) {
            $updates['label_flagged_at'] = null;
        }

        // Auto-statut en_attente / disponible
        $hadAny  = $asset->label_to_create || $asset->label_to_apply;
        $willHave = $newValue || (bool) $asset->label_to_apply;
        if (!$hadAny && $willHave) {
            // Cocher une case étiquette : passage en "en_attente"
            $updates['label_previous_status'] = $asset->status;
            $updates['status'] = 'en_attente';
        } elseif ($hadAny && !$willHave) {
            // Décocher la dernière case étiquette : passage automatique en "disponible"
            if ($asset->status === 'en_attente') {
                $updates['status'] = 'available';
            }
            $updates['label_previous_status'] = null;
        }

        $asset->update($updates);
        $asset->refresh();
        $completed = $this->autoCompleteLabelTasks($asset);
        return response()->json([
            'label_to_create'        => $asset->label_to_create,
            'label_flagged_at'       => $asset->label_flagged_at?->toIso8601String(),
            'label_flagged_at_label' => $asset->label_flagged_at?->format('d/m/Y H:i'),
            'tasks_auto_completed'   => $completed,
            'status'                 => $asset->status,
            'status_label'           => $asset->status_label,
        ]);
    }

    /** Toggle label_to_apply — admin only */
    public function toggleLabelApply(Asset $asset)
    {
        $newValue = !$asset->label_to_apply;
        $updates  = ['label_to_apply' => $newValue];

        // label_flagged_at
        if ($newValue && !$asset->label_flagged_at) {
            $updates['label_flagged_at'] = now();
        } elseif (!$newValue && !$asset->label_to_create) {
            $updates['label_flagged_at'] = null;
        }

        // Auto-statut en_attente / disponible
        $hadAny  = $asset->label_to_create || $asset->label_to_apply;
        $willHave = (bool) $asset->label_to_create || $newValue;
        if (!$hadAny && $willHave) {
            // Cocher une case étiquette : passage en "en_attente"
            $updates['label_previous_status'] = $asset->status;
            $updates['status'] = 'en_attente';
        } elseif ($hadAny && !$willHave) {
            // Décocher la dernière case étiquette : passage automatique en "disponible"
            if ($asset->status === 'en_attente') {
                $updates['status'] = 'available';
            }
            $updates['label_previous_status'] = null;
        }

        $asset->update($updates);
        $asset->refresh();
        $completed = $this->autoCompleteLabelTasks($asset);
        return response()->json([
            'label_to_apply'         => $asset->label_to_apply,
            'label_flagged_at'       => $asset->label_flagged_at?->toIso8601String(),
            'label_flagged_at_label' => $asset->label_flagged_at?->format('d/m/Y H:i'),
            'tasks_auto_completed'   => $completed,
            'status'                 => $asset->status,
            'status_label'           => $asset->status_label,
        ]);
    }

    /**
     * Marquer en masse les flags étiquettes — admin only
     */
    public function bulkLabel(Request $request)
    {
        $request->validate([
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer|exists:assets,id',
            'label_to_create' => 'nullable|boolean',
            'label_to_apply'  => 'nullable|boolean',
        ]);

        $setCreate  = $request->has('label_to_create');
        $setApply   = $request->has('label_to_apply');
        $wantCreate = $setCreate ? $request->boolean('label_to_create') : null;
        $wantApply  = $setApply  ? $request->boolean('label_to_apply')  : null;

        $assets = Asset::whereIn('id', $request->ids)->get();
        $count  = 0;

        foreach ($assets as $asset) {
            $updates = [];

            if ($setCreate) $updates['label_to_create'] = $wantCreate;
            if ($setApply)  $updates['label_to_apply']  = $wantApply;

            $finalCreate = $setCreate ? $wantCreate : (bool) $asset->label_to_create;
            $finalApply  = $setApply  ? $wantApply  : (bool) $asset->label_to_apply;
            $hadAny      = (bool) $asset->label_to_create || (bool) $asset->label_to_apply;
            $willHave    = $finalCreate || $finalApply;

            // label_flagged_at
            if ($willHave && !$asset->label_flagged_at) {
                $updates['label_flagged_at'] = now();
            } elseif (!$willHave) {
                $updates['label_flagged_at'] = null;
            }

            // Auto-statut en_attente
            if (!$hadAny && $willHave) {
                $updates['label_previous_status'] = $asset->status;
                $updates['status'] = 'en_attente';
            } elseif ($hadAny && !$willHave) {
                if ($asset->status === 'en_attente' && $asset->label_previous_status) {
                    $updates['status'] = $asset->label_previous_status;
                }
                $updates['label_previous_status'] = null;
            }

            if (!empty($updates)) {
                $asset->update($updates);
            }
            $count++;
        }

        return back()->with('success', "{$count} équipement(s) mis à jour.");
    }

    /**
     * Page dédiée : liste des équipements avec étiquette à créer/coller — admin only
     */
    public function labelsIndex()
    {
        $assets = Asset::with(['category'])
            ->where(fn($q) => $q->where('label_to_create', true)->orWhere('label_to_apply', true))
            ->orderByRaw('label_sort_order IS NULL ASC')  // nulls en dernier
            ->orderBy('label_sort_order', 'asc')
            ->orderBy('label_flagged_at', 'asc')
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'                     => $a->id,
                'name'                   => $a->name,
                'asset_tag'              => $a->asset_tag,
                'category'               => $a->category?->name,
                'location'               => $a->location,
                'status'                 => $a->status,
                'image'                  => $this->getImageUrl($a->image),
                'label_to_create'        => (bool) $a->label_to_create,
                'label_to_apply'         => (bool) $a->label_to_apply,
                'label_flagged_at'       => $a->label_flagged_at?->toIso8601String(),
                'label_flagged_at_label' => $a->label_flagged_at?->format('d/m/Y H:i'),
                'label_sort_order'       => $a->label_sort_order,
            ]);

        return \Inertia\Inertia::render('Assets/Labels', [
            'assets' => $assets,
        ]);
    }

    /** Enregistrer le message de vérification pour le retour — admin only */
    public function updateReturnCheckNote(Request $request, Asset $asset)
    {
        $request->validate([
            'return_check_note' => 'nullable|string|max:500',
        ]);
        $asset->update(['return_check_note' => $request->input('return_check_note') ?: null]);
        return response()->json(['return_check_note' => $asset->return_check_note]);
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
            'manufacturer_id'      => 'nullable|integer|exists:manufacturers,id',
            'place_id'             => 'nullable|integer|exists:places,id',
            'clear_place'          => 'nullable|boolean',
        ]);

        $updates = [];
        if ($request->has('status') && $request->status !== '')
            $updates['status'] = $request->status;
        if ($request->has('category_id'))
            $updates['category_id'] = $request->category_id ?: null;
        if ($request->has('location')) {
            $locationText = $request->location ?: null;
            $updates['location'] = $locationText;

            // Synchroniser place_id si un lieu correspond au nom
            if ($locationText) {
                $place = \App\Models\Place::where('name', $locationText)->first();
                $updates['place_id'] = $place?->id;
            } else {
                $updates['place_id'] = null;
            }
        }
        if ($request->has('is_reservable') && $request->is_reservable !== '')
            $updates['is_reservable'] = (bool) $request->is_reservable;
        if ($request->has('equipment_model_id'))
            $updates['equipment_model_id'] = $request->equipment_model_id ?: null;
        if ($request->has('manufacturer_id')) {
            $updates['manufacturer_id'] = $request->manufacturer_id ?: null;
            // Synchroniser le champ texte manufacturer pour cohérence
            if ($request->manufacturer_id) {
                $mfr = Manufacturer::find($request->manufacturer_id);
                if ($mfr) $updates['manufacturer'] = $mfr->name;
            } else {
                $updates['manufacturer'] = null;
            }
        }

        if (!empty($updates)) {
            Asset::whereIn('id', $request->ids)->update($updates);
        }

        // ── Mise à jour du lieu de rangement (sans créer d'affectation) ─────────
        if ($request->has('place_id') || $request->boolean('clear_place')) {
            $placeId = $request->place_id ?: null;
            $locationText = null;
            if ($placeId) {
                $place = \App\Models\Place::find($placeId);
                $locationText = $place?->name;
            }
            Asset::whereIn('id', $request->ids)->update([
                'place_id' => $placeId,
                'location' => $locationText,
            ]);
        }

        $count = count($request->ids);
        return back()->with('success', "{$count} équipement(s) mis à jour.");
    }

    /**
     * Affectation formelle en masse à un lieu (crée AssetPlaceAssignment pour chaque asset).
     */
    public function bulkAssignPlace(Request $request)
    {
        $data = $request->validate([
            'ids'                => 'required|array|min:1',
            'ids.*'              => 'integer|exists:assets,id',
            'place_id'           => 'required|integer|exists:places,id',
            'return_expected_at' => 'nullable|date',
            'notes'              => 'nullable|string|max:1000',
        ]);

        $place  = Place::findOrFail($data['place_id']);
        $assets = Asset::whereIn('id', $data['ids'])->get();

        // Pour un lieu de stockage, on fait juste la mise à jour texte sans affectation formelle
        if ($place->is_storage) {
            Asset::whereIn('id', $data['ids'])->update([
                'place_id' => $place->id,
                'location' => $place->name,
            ]);
            return back()->with('success', count($data['ids']) . " équipement(s) déplacés vers « {$place->name} ».");
        }

        $created = 0;
        $skippedMessages = [];

        foreach ($assets as $asset) {
            // Équipement retiré
            if ($asset->status === 'retired') {
                $skippedMessages[] = "« {$asset->name} » (retiré de l'inventaire)";
                continue;
            }

            // Déjà affecté à un autre lieu
            if ($asset->activePlaceAssignment()->exists()) {
                $existing = $asset->activePlaceAssignment()->with('place')->first();
                if ($existing->place_id === $place->id) {
                    // Même lieu — on met à jour la date et les notes si fournies
                    $existing->update(array_filter([
                        'return_expected_at' => $data['return_expected_at'] ?? $existing->return_expected_at,
                        'notes'              => $data['notes'] ?? $existing->notes,
                    ], fn($v) => $v !== null));
                    $asset->update(['place_id' => $place->id, 'location' => $place->name]);
                    $created++;
                    continue;
                }
                $skippedMessages[] = "« {$asset->name} » (déjà affecté à « {$existing->place->name} »)";
                continue;
            }

            // Créer l'affectation
            AssetPlaceAssignment::create([
                'asset_id'           => $asset->id,
                'place_id'           => $place->id,
                'assigned_by'        => Auth::id(),
                'assigned_at'        => now(),
                'return_expected_at' => $data['return_expected_at'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            // Mouvement
            $notesMvt = "Affecté à « {$place->name} »";
            if (!empty($data['return_expected_at'])) {
                $notesMvt .= ' jusqu\'au ' . \Carbon\Carbon::parse($data['return_expected_at'])->format('d/m/Y H:i');
            } else {
                $notesMvt .= ' (permanent)';
            }
            AssetMovement::create([
                'asset_id' => $asset->id,
                'user_id'  => Auth::id(),
                'action'   => 'place_assigned',
                'notes'    => $notesMvt,
            ]);

            // Mettre à jour place_id + location + statut si disponible
            $statusUpdate = $asset->status === 'available' ? ['status' => 'checked_out'] : [];
            $asset->update(array_merge(['place_id' => $place->id, 'location' => $place->name], $statusUpdate));

            $created++;
        }

        $msg = "{$created} équipement(s) affecté(s) à « {$place->name} ».";
        if (!empty($skippedMessages)) {
            $count = count($skippedMessages);
            $msg .= " {$count} ignoré(s) : " . implode(', ', array_slice($skippedMessages, 0, 3));
            if ($count > 3) $msg .= '…';
        }

        return back()->with('success', $msg);
    }

    public function bulkUpdateLabels(Request $request)
    {
        $request->validate([
            'ids'              => 'required|array|min:1',
            'ids.*'            => 'integer|exists:assets,id',
            'label_to_create'  => 'nullable|boolean',
            'label_to_apply'   => 'nullable|boolean',
        ]);

        $updates = [];
        if ($request->has('label_to_create') && $request->label_to_create !== null) {
            $updates['label_to_create'] = (bool) $request->label_to_create;
        }
        if ($request->has('label_to_apply') && $request->label_to_apply !== null) {
            $updates['label_to_apply'] = (bool) $request->label_to_apply;
        }

        if (!empty($updates)) {
            Asset::whereIn('id', $request->ids)->update($updates);

            // Si les deux flags passent à false, auto-compléter les tâches Todo liées
            $settingToCreate = $updates['label_to_create'] ?? null;
            $settingToApply  = $updates['label_to_apply']  ?? null;
            if ($settingToCreate === false && $settingToApply === false) {
                TodoTask::whereHas('assets', fn($q) => $q->whereIn('assets.id', $request->ids))
                    ->where('status', '!=', 'done')
                    ->update(['status' => 'done']);
            }
        }

        $count = count($request->ids);
        return back()->with('success', "Étiquettes mises à jour pour {$count} équipement(s).");
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

    // ─────────────────────────────────────────────────────────────────────────────
    // COMPOSANTS
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Ajouter un composant à un équipement.
     */
    public function storeComponent(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'quantity'    => 'required|integer|min:1|max:999',
        ]);

        // Si l'asset a un modèle, propager le composant à tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)
                ->orderBy('name')
                ->get();

            foreach ($assetsOfSameModel as $assetItem) {
                $maxSort = $assetItem->components()->max('sort_order') ?? 0;

                // Extraire le numéro de l'équipement (ex: "Trépied #1" -> "1")
                $assetNumber = null;
                if (preg_match('/#(\d+)/', $assetItem->name, $matches)) {
                    $assetNumber = $matches[1];
                }

                // Ajouter le numéro au nom du composant
                $componentName = $assetNumber ? $data['name'] . ' #' . $assetNumber : $data['name'];

                $assetItem->components()->create([
                    'name'        => $componentName,
                    'description' => $data['description'] ?? null,
                    'quantity'    => $data['quantity'],
                    'sort_order'  => $maxSort + 1,
                ]);
            }

            // Retourner le composant créé pour l'asset courant
            $component = $asset->components()->latest()->first();
        } else {
            // Pas de modèle : créer uniquement pour cet asset
            $maxSort = $asset->components()->max('sort_order') ?? 0;

            $component = $asset->components()->create([
                'name'        => $data['name'],
                'description' => $data['description'] ?? null,
                'quantity'    => $data['quantity'],
                'sort_order'  => $maxSort + 1,
            ]);
        }

        return response()->json([
            'id'          => $component->id,
            'name'        => $component->name,
            'description' => $component->description,
            'quantity'    => $component->quantity,
            'condition'   => $component->condition,
            'sort_order'  => $component->sort_order,
        ]);
    }

    /**
     * Modifier un composant.
     */
    public function updateComponent(Request $request, Asset $asset, AssetComponent $component)
    {
        if ($component->asset_id !== $asset->id) {
            abort(403, 'Ce composant n\'appartient pas à cet équipement.');
        }

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'quantity'    => 'required|integer|min:1|max:999',
        ]);

        $sortOrder = $component->sort_order;

        // Si l'asset a un modèle, propager la modification à tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)
                ->orderBy('name')
                ->get();

            foreach ($assetsOfSameModel as $assetItem) {
                // Trouver le composant correspondant (même sort_order)
                $comp = $assetItem->components()
                    ->where('sort_order', $sortOrder)
                    ->first();

                if ($comp) {
                    // Extraire le numéro de l'équipement
                    $assetNumber = null;
                    if (preg_match('/#(\d+)/', $assetItem->name, $matches)) {
                        $assetNumber = $matches[1];
                    }

                    // Mettre à jour avec le nouveau nom incrémenté
                    $componentName = $assetNumber ? $data['name'] . ' #' . $assetNumber : $data['name'];

                    $comp->update([
                        'name'        => $componentName,
                        'description' => $data['description'] ?? null,
                        'quantity'    => $data['quantity'],
                    ]);
                }
            }

            $component->refresh();
        } else {
            // Pas de modèle : modifier uniquement ce composant
            $component->update($data);
        }

        return response()->json([
            'id'          => $component->id,
            'name'        => $component->name,
            'description' => $component->description,
            'quantity'    => $component->quantity,
            'condition'   => $component->condition,
            'sort_order'  => $component->sort_order,
        ]);
    }

    /**
     * Supprimer un composant.
     */
    public function destroyComponent(Asset $asset, AssetComponent $component)
    {
        if ($component->asset_id !== $asset->id) {
            abort(403, 'Ce composant n\'appartient pas à cet équipement.');
        }

        $sortOrder = $component->sort_order;

        // Si l'asset a un modèle, propager la suppression à tous les équipements du même modèle
        if ($asset->equipment_model_id) {
            $assetsOfSameModel = Asset::where('equipment_model_id', $asset->equipment_model_id)->get();

            foreach ($assetsOfSameModel as $assetItem) {
                // Trouver et supprimer le composant correspondant (même sort_order)
                $comp = $assetItem->components()
                    ->where('sort_order', $sortOrder)
                    ->first();

                if ($comp) {
                    $comp->delete();
                }
            }
        } else {
            // Pas de modèle : supprimer uniquement ce composant
            $component->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mettre à jour l'état d'un composant.
     */
    public function updateComponentCondition(Request $request, Asset $asset, AssetComponent $component)
    {
        if ($component->asset_id !== $asset->id) {
            abort(403, 'Ce composant n\'appartient pas à cet équipement.');
        }

        $data = $request->validate([
            'condition' => 'nullable|in:good,damaged,defective,missing',
        ]);

        $component->update(['condition' => $data['condition']]);

        return response()->json(['success' => true]);
    }

    /**
     * Exporter une sélection d'équipements en PDF ou Excel (CSV).
     * Accessible à tous les utilisateurs authentifiés.
     */
    public function exportSelection(Request $request)
    {
        $request->validate([
            'format'  => 'required|in:pdf,excel',
            'ids'     => 'required|string',
            'columns' => 'nullable|string',
        ]);

        $ids = array_filter(explode(',', $request->ids));
        if (empty($ids)) {
            abort(400, 'Aucun équipement sélectionné');
        }

        $isAdmin = $request->user()->hasRole('admin');

        // Définir les colonnes disponibles
        $availableColumns = ['name', 'asset_tag', 'serial', 'category', 'model', 'manufacturer', 'location', 'status', 'checked_out_to'];

        // Parser et valider les colonnes demandées
        $requestedColumns = $request->columns ? explode(',', $request->columns) : $availableColumns;
        $columns = array_intersect($requestedColumns, $availableColumns);

        // Filtrer les colonnes admin-only pour les non-admin
        if (!$isAdmin) {
            $columns = array_diff($columns, ['checked_out_to']);
        }

        // Si aucune colonne valide, utiliser les colonnes par défaut
        if (empty($columns)) {
            $columns = array_diff($availableColumns, $isAdmin ? [] : ['checked_out_to']);
        }

        // Récupérer les équipements sélectionnés
        $assets = Asset::with(['category', 'equipmentModel', 'manufacturerModel', 'checkedOutTo'])
            ->whereIn('id', $ids)
            ->when(!$isAdmin, function ($q) {
                // Les utilisateurs non-admin ne peuvent exporter que les équipements visibles
                $hiddenStatusKeys = \App\Models\AssetStatus::allCached()
                    ->where('hide_from_users', true)
                    ->pluck('key')
                    ->toArray();
                if (!empty($hiddenStatusKeys)) {
                    $q->whereNotIn('status', $hiddenStatusKeys);
                }
            })
            ->orderBy('name')
            ->get();

        if ($assets->isEmpty()) {
            abort(404, 'Aucun équipement trouvé');
        }

        if ($request->format === 'pdf') {
            return $this->exportSelectionPdf($assets, $columns);
        } else {
            return $this->exportSelectionExcel($assets, $columns);
        }
    }

    /**
     * Générer un PDF listant les équipements sélectionnés.
     */
    private function exportSelectionPdf($assets, $columns)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.assets-selection-pdf', [
            'assets'  => $assets,
            'columns' => $columns,
            'date'    => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('equipements-selection-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Générer un fichier Excel (CSV) listant les équipements sélectionnés.
     */
    private function exportSelectionExcel($assets, $columns)
    {
        $filename = 'equipements-selection-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $columnLabels = [
            'name'           => 'Nom',
            'asset_tag'      => 'Tag',
            'serial'         => 'Numéro de série',
            'category'       => 'Catégorie',
            'model'          => 'Modèle',
            'manufacturer'   => 'Fabricant',
            'location'       => 'Emplacement',
            'status'         => 'Statut',
            'checked_out_to' => 'Affecté à',
        ];

        $callback = function () use ($assets, $columns, $columnLabels) {
            $file = fopen('php://output', 'w');

            // BOM UTF-8 pour Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // En-têtes (colonnes sélectionnées uniquement)
            $headers = [];
            foreach ($columns as $col) {
                $headers[] = $columnLabels[$col] ?? $col;
            }
            fputcsv($file, $headers, ';');

            // Données
            foreach ($assets as $asset) {
                $row = [];
                foreach ($columns as $col) {
                    switch ($col) {
                        case 'name':
                            $row[] = $asset->name;
                            break;
                        case 'asset_tag':
                            $row[] = $asset->asset_tag;
                            break;
                        case 'serial':
                            $row[] = $asset->serial ?? '';
                            break;
                        case 'category':
                            $row[] = $asset->category?->name ?? '';
                            break;
                        case 'model':
                            $row[] = $asset->equipmentModel?->name ?? $asset->model ?? '';
                            break;
                        case 'manufacturer':
                            $row[] = $asset->manufacturer?->name ?? $asset->manufacturer ?? '';
                            break;
                        case 'location':
                            $row[] = $asset->location ?? '';
                            break;
                        case 'status':
                            $row[] = $asset->status_label;
                            break;
                        case 'checked_out_to':
                            $row[] = $asset->checkedOutTo?->name ?? '';
                            break;
                        default:
                            $row[] = '';
                    }
                }
                fputcsv($file, $row, ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export des équipements sélectionnés en PDF.
     * Avec option de regroupement par modèle (groupByModel=true).
     */
    public function exportPdf(Request $request)
    {
        $isAdmin = $request->user()->hasRole('admin');

        $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer|exists:assets,id',
            'columns'      => 'nullable|array',
            'columns.*'    => 'string',
            'groupByModel' => 'nullable|boolean',
            'title'        => 'nullable|string|max:255',
        ]);

        $ids = $request->input('ids', []);
        $groupByModel = $request->boolean('groupByModel', false);
        $title = $request->input('title', 'Liste des Équipements');

        // Colonnes disponibles selon le rôle
        $allowedColumns = $isAdmin
            ? ['name', 'asset_tag', 'serial', 'category', 'model', 'manufacturer', 'location', 'status', 'checked_out_to']
            : ['name', 'category', 'model', 'manufacturer'];

        // Filtrer et valider les colonnes demandées
        $requestedColumns = $request->input('columns', []);
        $columns = !empty($requestedColumns)
            ? array_values(array_intersect($requestedColumns, $allowedColumns))
            : $allowedColumns;

        // Si aucune colonne valide, utiliser les colonnes par défaut selon le rôle
        if (empty($columns)) {
            $columns = $allowedColumns;
        }

        $assets = Asset::with(['category', 'equipmentModel', 'manufacturerModel', 'checkedOutTo'])
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();

        $date = now()->format('d/m/Y à H:i');

        if ($groupByModel) {
            // Regrouper par modèle et compter les quantités
            $grouped = $assets->groupBy(function ($asset) {
                // Clé de regroupement : category_id + model_id (ou nom de modèle si legacy)
                $modelKey = $asset->equipment_model_id ?? $asset->model ?? 'sans_modele';
                $categoryKey = $asset->category_id ?? 'sans_categorie';
                return $categoryKey . '|' . $modelKey;
            })->map(function ($group) use ($columns) {
                $first = $group->first();

                $result = [
                    'quantity' => $group->count(),
                    'assets'   => $group,
                ];

                // Ajouter uniquement les colonnes demandées
                if (in_array('name', $columns)) {
                    $result['names'] = $group->pluck('name')->values()->toArray();
                }
                if (in_array('asset_tag', $columns)) {
                    $result['asset_tags'] = $group->pluck('asset_tag')->values()->toArray();
                }
                if (in_array('category', $columns)) {
                    $result['category'] = $first->category;
                }
                if (in_array('model', $columns)) {
                    $result['model'] = $first->equipmentModel?->name ?? $first->model ?? '—';
                }
                if (in_array('manufacturer', $columns)) {
                    $result['manufacturer'] = $first->manufacturerModel?->name ?? $first->manufacturer ?? '—';
                }
                if (in_array('location', $columns)) {
                    $result['locations'] = $group->pluck('location')->unique()->filter()->values()->toArray();
                }
                if (in_array('status', $columns)) {
                    $result['statuses'] = $group->map(fn($a) => $a->status_label)->unique()->values()->toArray();
                }

                return $result;
            })->values();

            $pdf = Pdf::loadView('exports.assets-grouped-pdf', [
                'groups'  => $grouped,
                'date'    => $date,
                'columns' => $columns,
                'title'   => $title,
            ])->setPaper('a4', 'landscape');
        } else {
            // Export individuel classique
            $pdf = Pdf::loadView('exports.assets-selection-pdf', [
                'assets'  => $assets,
                'date'    => $date,
                'columns' => $columns,
                'title'   => $title,
            ])->setPaper('a4', 'landscape');
        }

        $filename = $groupByModel
            ? 'equipements-groupes-' . now()->format('Y-m-d_His') . '.pdf'
            : 'equipements-' . now()->format('Y-m-d_His') . '.pdf';

        return $pdf->download($filename);
    }
}
