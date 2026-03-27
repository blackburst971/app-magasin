<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Category;
use App\Models\EquipmentModel;
use App\Models\License;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class SnipeItBackupService
{
    private array $idMaps = [
        'manufacturers' => [],
        'categories'    => [],
        'models'        => [],
        'assets'        => [],
        'users'         => [],
    ];

    // ─────────────────────────────────────────────────────────────────────
    // API PUBLIQUE
    // ─────────────────────────────────────────────────────────────────────

    public function analyze(UploadedFile $file): array
    {
        set_time_limit(300);

        $token  = Str::uuid()->toString();
        $tmpDir = storage_path("app/temp/snipe_{$token}");
        mkdir($tmpDir, 0755, true);

        $file->move($tmpDir, 'backup.zip');

        $extractDir = $tmpDir . DIRECTORY_SEPARATOR . 'extracted';
        mkdir($extractDir, 0755, true);
        $this->extractZip($tmpDir . DIRECTORY_SEPARATOR . 'backup.zip', $extractDir);

        $sqlFile = $this->findSqlFile($extractDir);
        if (!$sqlFile) {
            $this->deleteDir($tmpDir);
            throw new \RuntimeException("Fichier SQL introuvable dans l'archive. Verifiez que c'est bien une sauvegarde Snipe-IT.");
        }

        $counts     = $this->countTableRows($sqlFile, ['manufacturers', 'models', 'categories', 'assets', 'licenses', 'users']);
        $imageCount = $this->countImages($extractDir);

        return [
            'token'       => $token,
            'counts'      => $counts,
            'image_count' => $imageCount,
        ];
    }

    public function import(string $token, array $options): array
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $tmpDir     = storage_path("app/temp/snipe_{$token}");
        $extractDir = $tmpDir . DIRECTORY_SEPARATOR . 'extracted';

        if (!is_dir($extractDir)) {
            throw new \RuntimeException("Session expiree. Re-uploadez le fichier.");
        }

        $sqlFile = $this->findSqlFile($extractDir);
        if (!$sqlFile) {
            throw new \RuntimeException("Fichier SQL introuvable.");
        }

        // Tables a charger selon les options
        $needed = ['status_labels'];
        if ($options['manufacturers'] ?? false) $needed[] = 'manufacturers';
        if ($options['categories'] ?? false)    $needed[] = 'categories';
        if ($options['models'] ?? false) {
            array_push($needed, 'models', 'manufacturers', 'categories');
        }
        if ($options['assets'] ?? false) {
            array_push($needed, 'assets', 'models', 'manufacturers', 'categories');
        }
        if ($options['licenses'] ?? false) $needed[] = 'licenses';
        if ($options['users'] ?? false)    $needed[] = 'users';

        $sqlData   = $this->parseSql($sqlFile, array_unique($needed));
        $withImages = (bool)($options['images'] ?? true);
        $stats     = [];
        $errors    = [];

        // Mapping statuts Snipe-IT (status_labels utilise des booléens)
        $statusMap = [];
        foreach ($sqlData['status_labels'] ?? [] as $row) {
            $statusMap[$row['id']] = $this->mapStatusLabel($row);
        }

        // 1. Fabricants
        if ($options['manufacturers'] ?? false) {
            $r = $this->importManufacturers($sqlData['manufacturers'] ?? [], $extractDir, $withImages);
            $stats['manufacturers'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        // 2. Categories
        if ($options['categories'] ?? false) {
            $r = $this->importCategories($sqlData['categories'] ?? []);
            $stats['categories'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        // 3. Modeles (dependances: manufacturers + categories)
        if ($options['models'] ?? false) {
            if (!($options['manufacturers'] ?? false)) {
                $this->buildMfrMap($sqlData['manufacturers'] ?? []);
            }
            if (!($options['categories'] ?? false)) {
                $this->buildCatMap($sqlData['categories'] ?? []);
            }
            $r = $this->importModels($sqlData['models'] ?? [], $extractDir, $withImages);
            $stats['models'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        // 4. Equipements
        if ($options['assets'] ?? false) {
            if (!($options['manufacturers'] ?? false)) $this->buildMfrMap($sqlData['manufacturers'] ?? []);
            if (!($options['categories'] ?? false))    $this->buildCatMap($sqlData['categories'] ?? []);
            if (!($options['models'] ?? false))        $this->buildModelMap($sqlData['models'] ?? []);
            $r = $this->importAssets($sqlData['assets'] ?? [], $statusMap, $extractDir, $withImages);
            $stats['assets'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        // 5. Licences
        if ($options['licenses'] ?? false) {
            $r = $this->importLicenses($sqlData['licenses'] ?? []);
            $stats['licenses'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        // 6. Utilisateurs
        if ($options['users'] ?? false) {
            $r = $this->importUsers($sqlData['users'] ?? []);
            $stats['users'] = $r;
            $errors = array_merge($errors, $r['errors']);
        }

        $this->deleteDir($tmpDir);

        return ['stats' => $stats, 'errors' => $errors];
    }

    public function cleanup(string $token): void
    {
        $tmpDir = storage_path("app/temp/snipe_{$token}");
        if (is_dir($tmpDir)) {
            $this->deleteDir($tmpDir);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // ETAPES D'IMPORT
    // ─────────────────────────────────────────────────────────────────────

    private function importManufacturers(array $rows, string $extractDir, bool $withImages): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            try {
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;

                $data = ['name' => $name];
                if (!empty($row['url'])) $data['website'] = $row['url'];

                if ($withImages && !empty($row['image'])) {
                    $img = $this->copyImage($extractDir, $row['image'], 'manufacturers');
                    if ($img) $data['image'] = $img;
                }

                $existing = Manufacturer::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                if ($existing) {
                    $upd = [];
                    if (!$existing->image && isset($data['image'])) $upd['image'] = $data['image'];
                    if (!$existing->website && isset($data['website'])) $upd['website'] = $data['website'];
                    if ($upd) $existing->update($upd);
                    $this->idMaps['manufacturers'][$row['id']] = $existing->id;
                    $updated++;
                } else {
                    $m = Manufacturer::create($data);
                    $this->idMaps['manufacturers'][$row['id']] = $m->id;
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Fabricant #{$row['id']}: " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    private function importCategories(array $rows): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            try {
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;

                $existing = Category::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
                if ($existing) {
                    $this->idMaps['categories'][$row['id']] = $existing->id;
                    $updated++;
                } else {
                    $c = Category::create(['name' => $name]);
                    $this->idMaps['categories'][$row['id']] = $c->id;
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Categorie #{$row['id']}: " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    private function importModels(array $rows, string $extractDir, bool $withImages): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            try {
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;

                $mfrId   = $this->idMaps['manufacturers'][$row['manufacturer_id'] ?? 0] ?? null;
                $catId   = $this->idMaps['categories'][$row['category_id'] ?? 0] ?? null;
                $mfrName = $mfrId ? Manufacturer::find($mfrId)?->name : null;

                $imageStorePath = null;
                if ($withImages && !empty($row['image'])) {
                    $imageStorePath = $this->copyImage($extractDir, $row['image'], 'models');
                }

                $query    = EquipmentModel::whereRaw('LOWER(name) = ?', [strtolower($name)]);
                if ($mfrId) $query->where('manufacturer_id', $mfrId);
                $existing = $query->first();

                if ($existing) {
                    $upd = [];
                    if (!$existing->manufacturer_id && $mfrId) $upd['manufacturer_id'] = $mfrId;
                    if (!$existing->category_id && $catId)     $upd['category_id'] = $catId;
                    if (!$existing->manufacturer && $mfrName)  $upd['manufacturer'] = $mfrName;
                    if (!$existing->image && $imageStorePath)  $upd['image'] = $imageStorePath;
                    if ($upd) $existing->update($upd);
                    $this->idMaps['models'][$row['id']] = $existing->id;
                    $updated++;
                } else {
                    $data = array_filter([
                        'name'            => $name,
                        'manufacturer_id' => $mfrId,
                        'manufacturer'    => $mfrName,
                        'category_id'     => $catId,
                        'image'           => $imageStorePath,
                    ], fn($v) => $v !== null);
                    $m = EquipmentModel::create($data);
                    $this->idMaps['models'][$row['id']] = $m->id;
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Modele #{$row['id']} ({$row['name']}): " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    private function importAssets(array $rows, array $statusMap, string $extractDir, bool $withImages): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            try {
                $assetTag = trim($row['asset_tag'] ?? '');
                if (empty($assetTag)) continue;

                $modelId = $this->idMaps['models'][$row['model_id'] ?? 0] ?? null;
                $status  = $statusMap[$row['status_id'] ?? null] ?? 'available';

                $data = array_filter([
                    'asset_tag'          => $assetTag,
                    'name'               => trim($row['name'] ?? '') ?: null,
                    'serial'             => trim($row['serial'] ?? '') ?: null,
                    'equipment_model_id' => $modelId,
                    'status'             => $status,
                    'purchase_date'      => $this->parseDate($row['purchase_date'] ?? null),
                    'purchase_cost'      => is_numeric($row['purchase_cost'] ?? null) ? $row['purchase_cost'] : null,
                    'notes'              => trim($row['notes'] ?? '') ?: null,
                    'is_reservable'      => (bool)($row['requestable'] ?? 0),
                ], fn($v) => $v !== null && $v !== false);

                if ($withImages && !empty($row['image'])) {
                    $img = $this->copyImage($extractDir, $row['image'], 'assets');
                    if ($img) $data['image'] = $img;
                }

                if (empty($data['name'])) {
                    $data['name'] = $modelId
                        ? (EquipmentModel::find($modelId)?->name ?? $assetTag)
                        : $assetTag;
                }

                $existing = Asset::where('asset_tag', $assetTag)->first();
                if ($existing) {
                    $existing->update($data);
                    $this->idMaps['assets'][$row['id']] = $existing->id;
                    $updated++;
                } else {
                    $asset = Asset::create($data);
                    AssetMovement::create([
                        'asset_id' => $asset->id,
                        'action'   => 'import',
                        'notes'    => 'Import sauvegarde Snipe-IT',
                    ]);
                    $this->idMaps['assets'][$row['id']] = $asset->id;
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Asset #{$row['id']} ({$row['asset_tag']}): " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    private function importLicenses(array $rows): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            try {
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;

                $data = array_filter([
                    'name'          => $name,
                    'license_key'   => trim($row['serial'] ?? '') ?: null,
                    'seats'         => max(1, (int)($row['seats'] ?? 1)),
                    'purchase_date' => $this->parseDate($row['purchase_date'] ?? null),
                    'expiry_date'   => $this->parseDate($row['expiration_date'] ?? null),
                    'purchase_cost' => is_numeric($row['purchase_cost'] ?? null) ? $row['purchase_cost'] : null,
                    'order_number'  => trim($row['order_number'] ?? $row['purchase_order'] ?? '') ?: null,
                    'notes'         => trim($row['notes'] ?? '') ?: null,
                ], fn($v) => $v !== null);

                $q        = License::where('name', $name);
                $existing = isset($data['license_key'])
                    ? $q->where('license_key', $data['license_key'])->first()
                    : $q->first();

                if ($existing) {
                    $existing->update($data);
                    $updated++;
                } else {
                    License::create($data);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = "Licence #{$row['id']}: " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    private function importUsers(array $rows): array
    {
        $imported = 0; $updated = 0; $errors = [];

        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            if (empty($row['email'])) continue;
            try {
                $email = strtolower(trim($row['email']));
                $fname = trim($row['first_name'] ?? '');
                $lname = trim($row['last_name'] ?? '');
                $name  = trim("$fname $lname") ?: $email;

                $existing = User::where('email', $email)->first();
                if ($existing) {
                    $this->idMaps['users'][$row['id']] = $existing->id;
                    $updated++;
                    continue;
                }

                $user = User::create([
                    'name'              => $name,
                    'email'             => $email,
                    'password'          => $row['password'] ?? Hash::make(Str::random(20)),
                    'email_verified_at' => now(),
                ]);
                $user->assignRole('etudiant');
                $this->idMaps['users'][$row['id']] = $user->id;
                $imported++;
            } catch (\Exception $e) {
                $errors[] = "Utilisateur #{$row['id']}: " . $e->getMessage();
            }
        }

        return compact('imported', 'updated', 'errors');
    }

    // ─────────────────────────────────────────────────────────────────────
    // CARTES D'IDS (dependances non importees)
    // ─────────────────────────────────────────────────────────────────────

    private function buildMfrMap(array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['name'])) continue;
            $m = Manufacturer::whereRaw('LOWER(name) = ?', [strtolower(trim($row['name']))])->first();
            if ($m) $this->idMaps['manufacturers'][$row['id']] = $m->id;
        }
    }

    private function buildCatMap(array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['name'])) continue;
            $c = Category::whereRaw('LOWER(name) = ?', [strtolower(trim($row['name']))])->first();
            if ($c) $this->idMaps['categories'][$row['id']] = $c->id;
        }
    }

    private function buildModelMap(array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['name'])) continue;
            $mfrId = $this->idMaps['manufacturers'][$row['manufacturer_id'] ?? 0] ?? null;
            $q     = EquipmentModel::whereRaw('LOWER(name) = ?', [strtolower(trim($row['name']))]);
            if ($mfrId) $q->where('manufacturer_id', $mfrId);
            $m = $q->first();
            if ($m) $this->idMaps['models'][$row['id']] = $m->id;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PARSEUR SQL
    // Le dump Snipe-IT utilise INSERT INTO `table` VALUES (...),(...)
    // SANS noms de colonnes — on doit lire les CREATE TABLE d'abord.
    // ─────────────────────────────────────────────────────────────────────

    private function countTableRows(string $sqlPath, array $tables): array
    {
        $counts  = array_fill_keys($tables, 0);
        $handle  = fopen($sqlPath, 'r');
        if (!$handle) return $counts;

        $current = null; // table courante dans un INSERT multi-ligne

        while (($line = fgets($handle)) !== false) {
            $t = ltrim($line);
            if (str_starts_with($t, '--') || str_starts_with($t, '/*') || $t === '') continue;

            if (preg_match('/^INSERT INTO\s+[`"]?(\w+)[`"]?/i', $t, $m)) {
                $current = in_array($m[1], $tables) ? $m[1] : null;
            }

            if ($current && str_starts_with(ltrim($line), '(')) {
                $counts[$current]++;
            }

            if (str_ends_with(rtrim($line), ';') && $current && !str_starts_with($t, 'INSERT')) {
                $current = null;
            }
        }
        fclose($handle);
        return $counts;
    }

    /**
     * Parse le fichier SQL : lit d'abord les CREATE TABLE pour connaitre
     * l'ordre des colonnes, puis parse les INSERT VALUES.
     */
    private function parseSql(string $sqlPath, array $tables): array
    {
        $data         = array_fill_keys($tables, []);
        $tableColumns = []; // table => [col1, col2, ...]

        $handle = fopen($sqlPath, 'r');
        if (!$handle) return $data;

        $buffer  = '';
        $inBlock = false;

        while (($line = fgets($handle)) !== false) {
            $t = ltrim($line);

            // Ignorer les commentaires autonomes
            if (!$inBlock && ($t === '' || str_starts_with($t, '--'))) continue;
            if (str_starts_with($t, '/*!') && str_ends_with(rtrim($t), '*/;')) continue;

            $buffer .= $line;
            $inBlock = true;

            if (str_ends_with(rtrim($buffer), ';')) {
                $stmt = trim($buffer);
                $buffer  = '';
                $inBlock = false;

                // CREATE TABLE → mémoriser les colonnes
                if (preg_match('/^CREATE TABLE\s+[`"]?(\w+)[`"]?/i', $stmt, $m)) {
                    $tname = $m[1];
                    if (in_array($tname, $tables)) {
                        $tableColumns[$tname] = $this->extractColumns($stmt);
                    }
                    continue;
                }

                // INSERT INTO → parser les valeurs
                if (preg_match('/^INSERT INTO\s+[`"]?(\w+)[`"]?/i', $stmt, $m)) {
                    $tname = $m[1];
                    if (!in_array($tname, $tables)) continue;
                    $cols  = $tableColumns[$tname] ?? [];
                    $this->processInsert($stmt, $tname, $cols, $data);
                }
            }
        }

        if ($inBlock && trim($buffer) !== '') {
            // Dernier bloc sans ; final
            $stmt = trim($buffer);
            if (preg_match('/^INSERT INTO\s+[`"]?(\w+)[`"]?/i', $stmt, $m)) {
                $tname = $m[1];
                if (in_array($tname, $tables)) {
                    $this->processInsert($stmt, $tname, $tableColumns[$tname] ?? [], $data);
                }
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * Extrait les noms de colonnes d'un CREATE TABLE.
     */
    private function extractColumns(string $stmt): array
    {
        $cols  = [];
        $lines = explode("\n", $stmt);
        foreach ($lines as $line) {
            $l = ltrim($line);
            // Une colonne commence par un backtick
            if (!str_starts_with($l, '`')) continue;
            if (preg_match('/^`(\w+)`/', $l, $m)) {
                $cols[] = $m[1];
            }
        }
        return $cols;
    }

    private function processInsert(string $stmt, string $table, array $columns, array &$data): void
    {
        // Trouver VALUES (avec ou sans liste de colonnes explicite)
        $valuesPos = stripos($stmt, 'VALUES');
        if ($valuesPos === false) return;

        // Vérifier si des colonnes sont déclarées dans l'INSERT (ex: INSERT INTO `t` (`c1`,`c2`) VALUES)
        $head = substr($stmt, 0, $valuesPos);
        if (preg_match('/\(\s*[`"]?\w+[`"]?\s*,/', $head)) {
            // Colonnes dans l'INSERT — les extraire
            if (preg_match('/\(([^)]+)\)\s+VALUES/is', $head, $cm)) {
                preg_match_all('/[`"]?(\w+)[`"]?/', $cm[1], $colm);
                $columns = $colm[1];
            }
        }

        $valuesPart = rtrim(trim(substr($stmt, $valuesPos + 6)), ';');
        $tuples     = $this->splitTuples($valuesPart);

        foreach ($tuples as $tuple) {
            $values = $this->parseMysqlRow($tuple);
            if (!empty($columns) && count($columns) === count($values)) {
                $data[$table][] = array_combine($columns, $values);
            } elseif (!empty($values)) {
                // Pas de colonnes connues → tableau positionnel (rarissime)
                $data[$table][] = $values;
            }
        }
    }

    private function splitTuples(string $str): array
    {
        $tuples = [];
        $depth  = 0;
        $inStr  = false;
        $start  = null;
        $len    = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if ($inStr) {
                if ($c === '\\') { $i++; continue; }
                if ($c === "'")  { $inStr = false; }
                continue;
            }
            if ($c === "'") { $inStr = true; continue; }
            if ($c === '(') {
                if ($depth === 0) $start = $i;
                $depth++;
            } elseif ($c === ')') {
                $depth--;
                if ($depth === 0 && $start !== null) {
                    $tuples[] = substr($str, $start + 1, $i - $start - 1);
                    $start    = null;
                }
            }
        }
        return $tuples;
    }

    private function parseMysqlRow(string $str): array
    {
        $values  = [];
        $current = '';
        $inStr   = false;
        $len     = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if ($inStr) {
                if ($c === '\\' && $i + 1 < $len) {
                    $next     = $str[$i + 1];
                    $current .= match($next) {
                        "'"  => "'",  "\\" => '\\', 'n' => "\n",
                        'r'  => "\r", 't'  => "\t", '0' => "\0",
                        default => $next,
                    };
                    $i++;
                    continue;
                }
                if ($c === "'") {
                    if ($i + 1 < $len && $str[$i + 1] === "'") {
                        $current .= "'";
                        $i++;
                        continue;
                    }
                    $inStr = false;
                    continue;
                }
                $current .= $c;
            } else {
                if ($c === "'") { $inStr = true; continue; }
                if ($c === ',') {
                    $values[] = $this->castValue($current);
                    $current  = '';
                    continue;
                }
                $current .= $c;
            }
        }
        $values[] = $this->castValue($current);
        return $values;
    }

    private function castValue(string $v): mixed
    {
        $v = trim($v);
        if ($v === 'NULL' || $v === '\N') return null;
        return $v;
    }

    // ─────────────────────────────────────────────────────────────────────
    // GESTION DES IMAGES
    // Le ZIP Snipe-IT (créé sous Windows) peut avoir des \ dans les chemins.
    // On utilise une recherche par nom de fichier pour s'adapter aux deux OS.
    // ─────────────────────────────────────────────────────────────────────

    private function copyImage(string $extractDir, string $snipeImageName, string $destSubDir): ?string
    {
        $filename = basename(str_replace('\\', '/', $snipeImageName));
        if (empty($filename)) return null;

        // Si le fichier existe déjà dans notre storage, ne pas re-copier
        if (Storage::disk('public')->exists($destSubDir . '/' . $filename)) {
            return $destSubDir . '/' . $filename;
        }

        $found = $this->findFileRecursive($extractDir, $filename);
        if (!$found) return null;

        Storage::disk('public')->makeDirectory($destSubDir);
        Storage::disk('public')->put($destSubDir . '/' . $filename, file_get_contents($found));

        return $destSubDir . '/' . $filename;
    }

    /**
     * Recherche un fichier par son nom (basename) dans toute l'arborescence.
     * Gère le cas macOS où les \ du ZIP deviennent partie du nom de fichier.
     */
    private function findFileRecursive(string $dir, string $filename): ?string
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $fname = $f->getFilename();
            // Correspondance exacte (Windows / Linux)
            if ($fname === $filename) return $f->getPathname();
            // Cas macOS : le \ du ZIP devient partie du nom, ex: "models\assetmodel-image-1.jpg"
            if (str_ends_with($fname, '\\' . $filename) || str_ends_with($fname, '/' . $filename)) {
                return $f->getPathname();
            }
        }
        return null;
    }

    private function countImages(string $dir): int
    {
        $count = 0;
        $exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        $it    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $fname = $f->getFilename();
            // Récupérer l'extension même si le nom contient des backslash
            $ext = strtolower(pathinfo(str_replace('\\', '/', $fname), PATHINFO_EXTENSION));
            if (in_array($ext, $exts)) $count++;
        }
        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Mapping statut Snipe-IT → notre statut.
     * status_labels utilise des colonnes booléennes (pas un champ type).
     * Colonnes : deployable, pending, archived
     */
    private function mapStatusLabel(array $row): string
    {
        if (!empty($row['deleted_at'])) return 'available';
        if ((int)($row['archived'] ?? 0) === 1) return 'retired';
        // Vérifier par le nom pour maintenance
        $name = strtolower($row['name'] ?? '');
        if (str_contains($name, 'maintenance') || str_contains($name, 'reparation') || str_contains($name, 'réparation')) {
            return 'maintenance';
        }
        return 'available';
    }

    private function extractZip(string $zipPath, string $destDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Impossible d'ouvrir l'archive ZIP.");
        }
        $zip->extractTo($destDir);
        $zip->close();
    }

    private function findSqlFile(string $dir): ?string
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            // Gérer les noms de fichiers avec backslash (ZIP Windows extrait sur macOS)
            $fname = str_replace('\\', '/', $f->getFilename());
            if (pathinfo($fname, PATHINFO_EXTENSION) === 'sql') return $f->getPathname();
        }
        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if (empty($value) || $value === '0000-00-00') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) return substr($value, 0, 10);
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }
}
