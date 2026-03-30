<?php
namespace App\Services;

use App\Models\Manufacturer;
use League\Csv\Reader;
use Illuminate\Http\UploadedFile;

/**
 * Import de fabricants depuis un CSV
 * Colonnes supportées : name/nom, description, website/site_web/url
 */
class ManufacturerImportService
{
    protected array $columns = [
        'name'        => ['name', 'nom', 'manufacturer', 'fabricant', 'brand', 'marque'],
        'description' => ['description', 'desc', 'details', 'détails'],
        'website'     => ['website', 'site_web', 'url', 'site', 'web', 'lien', 'link'],
    ];

    public function preview(UploadedFile $file): array
    {
        $csv     = Reader::createFromPath($file->getRealPath());
        $csv->setHeaderOffset(0);
        $headers = $csv->getHeader();
        $mapping = $this->autoDetectMapping($headers);
        $rows    = iterator_to_array($csv->getRecords());
        $sample  = array_slice($rows, 0, 5);

        return [
            'headers' => $headers,
            'mapping' => $mapping,
            'sample'  => $sample,
            'total'   => count($rows),
        ];
    }

    public function import(UploadedFile $file, array $mapping): array
    {
        $csv = Reader::createFromPath($file->getRealPath());
        $csv->setHeaderOffset(0);

        $imported = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($csv->getRecords() as $i => $row) {
            try {
                $name = trim($row[$mapping['name'] ?? ''] ?? '');
                if (empty($name)) { $skipped++; continue; }

                $data = ['name' => $name];
                if (!empty($mapping['description']) && !empty($row[$mapping['description']])) {
                    $data['description'] = trim($row[$mapping['description']]);
                }
                if (!empty($mapping['website']) && !empty($row[$mapping['website']])) {
                    $url = trim($row[$mapping['website']]);
                    if ($url && !str_starts_with($url, 'http')) $url = 'https://' . $url;
                    $data['website'] = $url;
                }

                $existing = Manufacturer::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                if ($existing) {
                    $upd = [];
                    if (empty($existing->description) && isset($data['description'])) $upd['description'] = $data['description'];
                    if (empty($existing->website)     && isset($data['website']))     $upd['website']     = $data['website'];
                    if ($upd) $existing->update($upd);
                    $updated++;
                } else {
                    Manufacturer::create($data);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Ligne " . ($i + 2) . " : " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'skipped', 'errors');
    }

    private function autoDetectMapping(array $headers): array
    {
        $mapping = [];
        foreach ($this->columns as $field => $aliases) {
            foreach ($headers as $header) {
                if (in_array(strtolower(trim($header)), $aliases)) {
                    $mapping[$field] = $header;
                    break;
                }
            }
        }
        return $mapping;
    }
}
