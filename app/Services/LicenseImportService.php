<?php
namespace App\Services;

use App\Models\License;
use App\Models\Supplier;
use League\Csv\Reader;
use Illuminate\Http\UploadedFile;

/**
 * Import de licences logicielles depuis un export CSV Snipe-IT
 * Colonnes Snipe-IT supportées (auto-détection) :
 *   id, name, serial, purchase_date, purchase_cost, expiration_date,
 *   seats, min_amt, company, manufacturer, supplier, vendor,
 *   order_number, notes, category, user_can_checkout,
 *   checkout_count, checkin_count, termination_date,
 *   version, depreciation, reassignable, maintained
 */
class LicenseImportService
{
    // Colonnes Snipe-IT → champs License
    protected array $columns = [
        'name'          => ['name', 'nom', 'software name', 'licence', 'license name'],
        'license_key'   => ['serial', 'key', 'license key', 'serial number', 'clé', 'cle', 'product key'],
        'version'       => ['version', 'ver'],
        'manufacturer'  => ['manufacturer', 'fabricant', 'editeur', 'éditeur', 'vendor name', 'publisher'],
        'seats'         => ['seats', 'qty', 'quantity', 'nb postes', 'nb_postes', 'licences'],
        'purchase_date' => ['purchase date', 'purchase_date', 'date achat', 'date_achat', 'purchased'],
        'purchase_cost' => ['purchase cost', 'purchase_cost', 'cost', 'cout', 'coût', 'prix'],
        'expiry_date'   => ['expiration date', 'expiry date', 'expiry_date', 'expiration_date',
                            'date expiration', 'date_expiration', 'expire', 'expires',
                            'termination_date', 'term date'],
        'order_number'  => ['order number', 'order_number', 'po number', 'po', 'order no', 'bon commande'],
        'notes'         => ['notes', 'note', 'remarques', 'comments'],
        'supplier'      => ['supplier', 'vendor', 'fournisseur'],
        'type'          => ['category', 'categorie', 'type', 'license type', 'licence type'],
        'is_cloud'      => ['user_can_checkout', 'cloud', 'saas', 'is_cloud', 'reassignable'],
    ];

    /** Types Snipe-IT → enum type License */
    protected array $typeMap = [
        'software'        => 'per_seat',
        'logiciel'        => 'per_seat',
        'per seat'        => 'per_seat',
        'per_seat'        => 'per_seat',
        'named user'      => 'per_seat',
        'volume'          => 'volume',
        'oem'             => 'oem',
        'cloud'           => 'cloud',
        'saas'            => 'cloud',
        'subscription'    => 'cloud',
        'abonnement'      => 'cloud',
        'dongle'          => 'dongle',
        'open source'     => 'open_source',
        'open_source'     => 'open_source',
        'gratuit'         => 'open_source',
    ];

    /* ─── API publique ─────────────────────────────────────────────────────── */

    public function preview(UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();
        $records = iterator_to_array($csv->getRecords());
        return [
            'headers'           => $headers,
            'preview'           => array_values(array_slice($records, 0, 5)),
            'suggested_mapping' => $this->autoDetectMapping($headers),
            'total_rows'        => count($records),
        ];
    }

    public function import(UploadedFile $file, array $mapping): array
    {
        $csv = Reader::createFromPath($file->getPathname(), 'r');
        $csv->setHeaderOffset(0);
        $imported = 0; $updated = 0; $errors = [];

        foreach ($csv->getRecords() as $i => $record) {
            try {
                $data = $this->mapRecord($record, $mapping);
                if (empty($data['name'])) {
                    $errors[] = "Ligne " . ($i + 2) . " : colonne 'name' manquante — ligne ignorée";
                    continue;
                }

                // Résoudre le fournisseur
                if (!empty($data['_supplier_name'])) {
                    $supplier = Supplier::firstOrCreate(
                        ['name' => $data['_supplier_name']],
                        ['name' => $data['_supplier_name']]
                    );
                    $data['supplier_id'] = $supplier->id;
                }
                unset($data['_supplier_name']);

                // Normaliser les dates
                foreach (['purchase_date', 'expiry_date'] as $dateField) {
                    if (!empty($data[$dateField])) {
                        try {
                            $data[$dateField] = date('Y-m-d', strtotime($data[$dateField])) ?: null;
                        } catch (\Exception $e) {
                            $data[$dateField] = null;
                        }
                    }
                }

                // Normaliser le coût
                if (isset($data['purchase_cost'])) {
                    $data['purchase_cost'] = floatval(str_replace([',', ' ', '€', '$'], ['.', '', '', ''], $data['purchase_cost'])) ?: null;
                }

                // Normaliser les sièges
                if (isset($data['seats'])) {
                    $seats = intval($data['seats']);
                    $data['seats'] = $seats > 0 ? $seats : null;
                }

                // Chercher une licence existante par nom exact (case-insensitive)
                $existing = License::whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
                    ->when(!empty($data['version']), fn($q) => $q->where('version', $data['version']))
                    ->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    License::create($data);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Ligne " . ($i + 2) . " : " . $e->getMessage();
            }
        }

        return ['imported' => $imported, 'updated' => $updated, 'errors' => $errors, 'total' => $imported + $updated];
    }

    /* ─── Méthodes privées ─────────────────────────────────────────────────── */

    private function autoDetectMapping(array $headers): array
    {
        $mapping = [];
        foreach ($headers as $header) {
            $lower = strtolower(trim($header));
            foreach ($this->columns as $field => $aliases) {
                if (in_array($lower, $aliases)) {
                    $mapping[$header] = $field;
                    break;
                }
            }
        }
        return $mapping;
    }

    private function mapRecord(array $record, array $mapping): array
    {
        $data = ['type' => 'per_seat']; // défaut

        foreach ($mapping as $csvCol => $dbField) {
            if (empty($dbField) || $dbField === '__ignore__') continue;
            $value = trim($record[$csvCol] ?? '');
            if ($value === '' || $value === 'N/A' || $value === '-') continue;

            switch ($dbField) {
                case 'supplier':
                    $data['_supplier_name'] = $value;
                    break;
                case 'type':
                    $lower = strtolower($value);
                    $data['type'] = $this->typeMap[$lower] ?? 'per_seat';
                    break;
                case 'is_cloud':
                    $lower = strtolower($value);
                    $data['is_cloud'] = in_array($lower, ['true', '1', 'yes', 'oui', 'on']);
                    break;
                default:
                    $data[$dbField] = $value;
            }
        }

        return $data;
    }
}
