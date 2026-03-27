<?php
namespace App\Services;
use App\Models\Asset;
use App\Models\Category;
use App\Models\AssetMovement;
use App\Models\EquipmentModel;
use App\Models\Manufacturer;
use League\Csv\Reader;
use Illuminate\Http\UploadedFile;

class SnipeItImportService
{
    // Standard Snipe-IT CSV column names (English + French, case-insensitive)
    protected array $snipeColumns = [
        'name'           => ['name', 'asset name', 'nom', 'asset name (snipe-it)'],
        'asset_tag'      => ['asset tag', 'asset_tag', 'tag', 'numero', 'asset no', 'asset number', 'tag equipement'],
        'serial'         => ['serial', 'serial number', 'serial_number', 'numero de serie', 's/n', 'sn', 'serial no'],
        'equipment_model'=> ['model', 'model name', 'modele', 'equipment model', 'model.name', 'modèle'],
        'model'          => ['model no.', 'model no', 'model number', 'model (texte)', 'model_text', 'model number (snipe-it)'],
        'manufacturer'   => ['manufacturer', 'fabricant', 'marque', 'brand', 'make'],
        'category'       => ['category', 'categorie', 'type', 'catégorie'],
        'location'       => ['location', 'emplacement', 'lieu', 'site', 'default location'],
        'status'         => ['status', 'status_label', 'statut', 'etat', 'état', 'status name'],
        'purchase_date'  => ['purchase date', 'purchase_date', 'date achat', 'purchased', 'date d\'achat'],
        'purchase_cost'  => ['purchase cost', 'purchase_cost', 'cout', 'coût', 'cost', 'price', 'prix'],
        'order_number'   => ['order number', 'order_number', 'order no', 'po number', 'po', 'bon commande'],
        'warranty'       => ['warranty months', 'warranty', 'garantie', 'warranty_months'],
        'notes'          => ['notes', 'note', 'remarques', 'comments', 'description'],
        'image'          => ['image', 'image_url', 'photo', 'photo_url', 'picture', 'img'],
        'is_reservable'  => ['demandable', 'requestable', 'reservable', 'réservable', 'is_reservable', 'empruntable', 'requestable (snipe-it)'],
    ];

    public function preview(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        $preview = array_slice($records, 0, 5);
        $mapping = $this->autoDetectMapping($headers);
        return [
            'headers'           => $headers,
            'preview'           => array_values($preview),
            'suggested_mapping' => $mapping,
            'total_rows'        => count($records),
        ];
    }

    public function import(UploadedFile $file, array $mapping): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $imported = 0; $updated = 0; $errors = [];

        foreach ($csv->getRecords() as $i => $record) {
            try {
                $data = $this->mapRecord($record, $mapping);
                if (empty($data['asset_tag'])) {
                    $data['asset_tag'] = 'IMPORT-' . ($i + 1);
                }

                // Résoudre la catégorie → category_id
                if (!empty($data['category_name'])) {
                    $category = Category::firstOrCreate(['name' => $data['category_name']]);
                    $data['category_id'] = $category->id;
                }
                unset($data['category_name']);

                // Résoudre le fabricant → manufacturer_id (entité Manufacturer)
                if (!empty($data['manufacturer_name'])) {
                    $mfr = Manufacturer::firstOrCreate(['name' => trim($data['manufacturer_name'])]);
                    $data['manufacturer_id'] = $mfr->id;
                    // Conserver la chaîne pour compatibilité rétroactive
                    $data['manufacturer'] = $mfr->name;
                    unset($data['manufacturer_name']);
                }

                // Résoudre le modèle d'équipement → equipment_model_id
                if (!empty($data['equipment_model_name'])) {
                    $data['equipment_model_id'] = $this->resolveEquipmentModel(
                        $data['equipment_model_name'],
                        $data['manufacturer_id'] ?? null,
                        $data['category_id']     ?? null,
                        $data['manufacturer']    ?? null,
                    );
                    unset($data['equipment_model_name']);
                }

                // Mapper le statut
                $data['status'] = $this->mapStatus($data['status'] ?? 'available');

                // Normaliser la date d'achat (DD/MM/YYYY ou MM/DD/YYYY → YYYY-MM-DD)
                if (!empty($data['purchase_date'])) {
                    $data['purchase_date'] = $this->normalizeDate($data['purchase_date']);
                }

                // Normaliser le coût d'achat (virgule → point, espaces supprimés)
                if (isset($data['purchase_cost'])) {
                    $cost = str_replace([' ', "\u{00A0}"], '', $data['purchase_cost']);
                    $cost = str_replace(',', '.', $cost);
                    $cost = preg_replace('/[^0-9.]/', '', $cost);
                    $data['purchase_cost'] = is_numeric($cost) ? $cost : null;
                }

                // Champs supplémentaires non mappés vers des colonnes Asset standard
                unset($data['order_number'], $data['warranty']);

                $existing = Asset::where('asset_tag', $data['asset_tag'])->first();
                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    $asset = Asset::create($data);
                    AssetMovement::create(['asset_id' => $asset->id, 'action' => 'import', 'notes' => 'Import Snipe-IT']);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Ligne " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors, 'total' => $imported + $updated];
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if (empty($value)) return null;

        // Déjà au format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        // Format DD/MM/YYYY ou DD-MM-YYYY
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m)) {
            // Si le 2e groupe > 12, c'est forcément MM/DD — sinon on assume DD/MM (français)
            if ((int)$m[2] > 12) {
                return "{$m[3]}-{$m[1]}-{$m[2]}";
            }
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Tentative générique via strtotime
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function autoDetectMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $headerLower = strtolower(trim($header));
            foreach ($this->snipeColumns as $field => $aliases) {
                if (in_array($headerLower, $aliases)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }
        return $mapping;
    }

    private function mapRecord(array $record, array $mapping): array
    {
        $data = [];
        foreach ($mapping as $csvCol => $dbField) {
            if (empty($dbField)) continue; // colonne ignorée
            $value = trim($record[$csvCol] ?? '');
            if ($dbField === 'category') {
                $data['category_name'] = $value;
            } elseif ($dbField === 'equipment_model') {
                $data['equipment_model_name'] = $value;
            } elseif ($dbField === 'manufacturer') {
                // Stocker sous manufacturer_name pour la résolution en entité Manufacturer
                if (!empty($value)) $data['manufacturer_name'] = $value;
            } elseif ($dbField === 'image') {
                if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                    $data['image'] = $value;
                }
            } elseif ($dbField === 'is_reservable') {
                // Normaliser vrai/true/1/oui → 1, tout autre valeur → 0
                $lower = strtolower(trim($value));
                $data['is_reservable'] = in_array($lower, ['true', 'vrai', '1', 'oui', 'yes', 'on']) ? 1 : 0;
            } elseif (!empty($value)) {
                $data[$dbField] = $value;
            }
        }
        return $data;
    }

    /**
     * Résout ou crée un EquipmentModel par nom.
     * Lie correctement le manufacturer_id (entité) à la fois sur le modèle et sur les assets.
     */
    private function resolveEquipmentModel(
        string $name,
        ?int $manufacturerId = null,
        ?int $categoryId = null,
        ?string $manufacturerName = null
    ): int {
        $name = trim($name);

        // Résoudre le manufacturer_id si pas encore fait
        if (!$manufacturerId && $manufacturerName) {
            $mfr = Manufacturer::firstOrCreate(['name' => trim($manufacturerName)]);
            $manufacturerId = $mfr->id;
        }

        // Recherche case-insensitive
        $model = EquipmentModel::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if (!$model) {
            $model = EquipmentModel::create([
                'name'            => $name,
                'manufacturer'    => $manufacturerName,
                'manufacturer_id' => $manufacturerId,
                'category_id'     => $categoryId,
            ]);
        } else {
            // Mettre à jour manufacturer_id si manquant
            $updates = [];
            if (!$model->manufacturer_id && $manufacturerId) {
                $updates['manufacturer_id'] = $manufacturerId;
            }
            if (!$model->manufacturer && $manufacturerName) {
                $updates['manufacturer'] = $manufacturerName;
            }
            if ($updates) $model->update($updates);
        }

        return $model->id;
    }

    private function mapStatus(string $status): string
    {
        $map = [
            'ready to deploy' => 'available', 'deployable' => 'available', 'disponible' => 'available',
            'deployed'        => 'checked_out', 'en cours' => 'checked_out', 'sorti' => 'checked_out',
            'archived'        => 'retired', 'retired' => 'retired', 'retiré' => 'retired',
            'maintenance'     => 'maintenance', 'broken' => 'maintenance',
        ];
        return $map[strtolower($status)] ?? 'available';
    }
}
