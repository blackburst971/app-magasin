<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BackupController extends Controller
{
    private string $backupDir;
    private string $dbPath;
    private string $storagePath;

    public function __construct()
    {
        $this->backupDir   = storage_path('app/backups');
        $this->dbPath      = database_path('database.sqlite');
        $this->storagePath = storage_path('app/public');
    }

    // ─── Liste des sauvegardes ───────────────────────────────────────────────

    public function index()
    {
        $backups = $this->listBackups();

        return Inertia::render('Admin/Backups', [
            'backups'        => $backups,
            'dbSizeFormatted' => file_exists($this->dbPath)
                ? $this->formatSize(filesize($this->dbPath))
                : '—',
            'storageSizeFormatted' => is_dir($this->storagePath)
                ? $this->formatSize($this->dirSize($this->storagePath))
                : '—',
        ]);
    }

    // ─── Créer une sauvegarde ────────────────────────────────────────────────

    public function create(Request $request)
    {
        $this->ensureBackupDir();

        $filename = 'backup_' . date('Ymd_His') . '.zip';
        $zipPath  = $this->backupDir . '/' . $filename;

        $error = $this->buildZip($zipPath);
        if ($error) {
            return back()->withErrors(['error' => $error]);
        }

        // Téléchargement direct si demandé
        if ($request->boolean('download')) {
            return response()->download($zipPath, $filename);
        }

        return back()->with('success', "Sauvegarde créée : {$filename}");
    }

    // ─── Télécharger une sauvegarde ─────────────────────────────────────────

    public function download(string $filename)
    {
        $this->validateFilename($filename);
        $path = $this->backupDir . '/' . $filename;
        abort_if(!file_exists($path), 404);
        return response()->download($path, $filename);
    }

    // ─── Restaurer une sauvegarde (upload) ──────────────────────────────────

    public function restore(Request $request)
    {
        $request->validate([
            'backup' => 'required|file|max:524288', // 512 Mo max
        ]);

        $uploadedFile = $request->file('backup');

        // Vérifier que c'est un ZIP valide
        $zip = new \ZipArchive();
        if ($zip->open($uploadedFile->getPathname()) !== true) {
            return back()->withErrors(['backup' => 'Fichier invalide ou corrompu.']);
        }

        // Vérifier qu'il contient une base de données
        $hasDb = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($zip->getNameIndex($i) === 'database/database.sqlite') {
                $hasDb = true;
                break;
            }
        }

        if (!$hasDb) {
            $zip->close();
            return back()->withErrors(['backup' => 'Ce fichier n\'est pas une sauvegarde valide (base de données manquante).']);
        }

        // Sauvegarde de sécurité avant restauration
        $this->ensureBackupDir();
        $safetyPath = $this->backupDir . '/pre_restore_' . date('Ymd_His') . '.zip';
        $this->buildZip($safetyPath);

        // Extraire dans un dossier temporaire
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app_restore_' . time();
        mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();

        // ── Restaurer la base de données ────────────────────────────────────
        $restoredDb = $tmpDir . '/database/database.sqlite';
        if (file_exists($restoredDb)) {
            DB::disconnect();
            copy($restoredDb, $this->dbPath);
            DB::reconnect();
        }

        // ── Restaurer les fichiers storage ──────────────────────────────────
        $restoredStorage = $tmpDir . '/storage/app/public';
        if (is_dir($restoredStorage)) {
            $this->copyDir($restoredStorage, $this->storagePath);
        }

        // Nettoyer
        $this->deleteDir($tmpDir);

        // Appliquer les éventuelles nouvelles migrations
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {}

        Artisan::call('cache:clear');

        return back()->with('success', 'Restauration effectuée. Une sauvegarde de sécurité a été créée automatiquement avant la restauration.');
    }

    // ─── Restaurer depuis une sauvegarde existante sur le serveur ───────────

    public function restoreExisting(string $filename)
    {
        $this->validateFilename($filename);
        $zipPath = $this->backupDir . '/' . $filename;
        abort_if(!file_exists($zipPath), 404);

        // Sauvegarde de sécurité
        $safetyPath = $this->backupDir . '/pre_restore_' . date('Ymd_His') . '.zip';
        $this->buildZip($safetyPath);

        $zip = new \ZipArchive();
        abort_if($zip->open($zipPath) !== true, 422);

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'app_restore_' . time();
        mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();

        $restoredDb = $tmpDir . '/database/database.sqlite';
        if (file_exists($restoredDb)) {
            DB::disconnect();
            copy($restoredDb, $this->dbPath);
            DB::reconnect();
        }

        $restoredStorage = $tmpDir . '/storage/app/public';
        if (is_dir($restoredStorage)) {
            $this->copyDir($restoredStorage, $this->storagePath);
        }

        $this->deleteDir($tmpDir);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {}

        Artisan::call('cache:clear');

        return back()->with('success', "Sauvegarde « {$filename} » restaurée avec succès.");
    }

    // ─── Supprimer une sauvegarde ────────────────────────────────────────────

    public function destroy(string $filename)
    {
        $this->validateFilename($filename);
        $path = $this->backupDir . '/' . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
        return back()->with('success', 'Sauvegarde supprimée.');
    }

    // ─── Utilitaires ─────────────────────────────────────────────────────────

    private function listBackups(): array
    {
        if (!is_dir($this->backupDir)) return [];
        $files = glob($this->backupDir . '/*.zip') ?: [];
        $list  = [];
        foreach ($files as $file) {
            $list[] = [
                'name'       => basename($file),
                'size'       => $this->formatSize(filesize($file)),
                'created_at' => date('d/m/Y H:i', filemtime($file)),
                'ts'         => filemtime($file),
            ];
        }
        usort($list, fn($a, $b) => $b['ts'] - $a['ts']);
        return $list;
    }

    private function buildZip(string $zipPath): ?string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return 'Impossible de créer le fichier ZIP (permissions ?)';
        }

        if (file_exists($this->dbPath)) {
            $zip->addFile($this->dbPath, 'database/database.sqlite');
        }

        if (is_dir($this->storagePath)) {
            $this->addDirToZip($zip, $this->storagePath, 'storage/app/public');
        }

        $zip->close();
        return null;
    }

    private function addDirToZip(\ZipArchive $zip, string $dir, string $zipBase): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                $zip->addFile($file->getPathname(), $zipBase . '/' . $relative);
            }
        }
    }

    private function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) mkdir($dst, 0755, true);
        foreach (scandir($src) as $item) {
            if ($item === '.' || $item === '..') continue;
            $s = $src . '/' . $item;
            $d = $dst . '/' . $item;
            is_dir($s) ? $this->copyDir($s, $d) : copy($s, $d);
        }
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function ensureBackupDir(): void
    {
        if (!is_dir($this->backupDir)) mkdir($this->backupDir, 0755, true);
    }

    private function validateFilename(string $filename): void
    {
        abort_if(
            str_contains($filename, '..') || !preg_match('/^[\w\-_]+\.zip$/', $filename),
            403,
            'Nom de fichier invalide.'
        );
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1_024)           return $bytes . ' o';
        if ($bytes < 1_048_576)       return round($bytes / 1_024, 1) . ' Ko';
        if ($bytes < 1_073_741_824)   return round($bytes / 1_048_576, 1) . ' Mo';
        return round($bytes / 1_073_741_824, 2) . ' Go';
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $size += $file->getSize();
        }
        return $size;
    }
}
