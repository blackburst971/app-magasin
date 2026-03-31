<?php

namespace App\Http\Controllers;

use App\Services\SnipeItBackupService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SnipeItBackupController extends Controller
{
    public function index()
    {
        return Inertia::render('Import/SnipeBackup');
    }

    /**
     * Télécharge et analyse la sauvegarde ZIP.
     * Retourne les compteurs par type de données + token de session.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:512000', // 500 Mo max
        ]);

        try {
            $service = new SnipeItBackupService();
            $result  = $service->analyze($request->file('file'));

            // Stocker le token en session pour sécuriser l'étape d'import
            session(['snipe_backup_token' => $result['token']]);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Lance l'import avec les options sélectionnées par l'utilisateur.
     */
    public function import(Request $request)
    {
        $request->validate([
            'token'   => 'required|string',
            'options' => 'required|array',
        ]);

        // Vérifier que le token correspond à la session
        if ($request->token !== session('snipe_backup_token')) {
            return response()->json(['error' => 'Token invalide ou session expirée.'], 403);
        }

        try {
            $service = new SnipeItBackupService();
            $result  = $service->import($request->token, $request->options);

            session()->forget('snipe_backup_token');

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Propage les images des modèles vers les équipements sans image.
     * Utile pour corriger les équipements importés avant que les images soient disponibles.
     */
    public function syncImages()
    {
        try {
            $service = new SnipeItBackupService();
            $count   = $service->propagateImages();
            return response()->json(['updated' => $count]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Annule l'import et nettoie les fichiers temporaires.
     */
    public function cancel(Request $request)
    {
        $token = session('snipe_backup_token');
        if ($token) {
            (new SnipeItBackupService())->cleanup($token);
            session()->forget('snipe_backup_token');
        }
        return response()->json(['ok' => true]);
    }
}
