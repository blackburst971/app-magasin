<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\ScanSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ScanSessionController extends Controller
{
    /**
     * Créer une nouvelle session de scan apairé.
     * Retourne le token, l'URL mobile et le QR code SVG.
     */
    public function store(Request $request)
    {
        // Fermer les sessions actives existantes de l'utilisateur
        ScanSession::where('user_id', Auth::id())
            ->where('status', 'active')
            ->update(['status' => 'closed']);

        $token = Str::random(40);

        ScanSession::create([
            'token'         => $token,
            'user_id'       => Auth::id(),
            'status'        => 'active',
            'expires_at'    => now()->addMinutes(30),
            'scanned_items' => [],
        ]);

        $mobileUrl = url('/scan/mobile/' . $token);

        // Générer le QR code SVG
        // (string) requis : QrCode::generate() retourne HtmlString, non sérialisable en JSON
        $qrSvg = (string) QrCode::format('svg')->size(260)->generate($mobileUrl);
        // Supprimer la déclaration XML pour un rendu correct via v-html / innerHTML HTML5
        $qrSvg = preg_replace('/<\?xml[^>]*\?>/', '', $qrSvg);

        return response()->json([
            'token'      => $token,
            'mobile_url' => $mobileUrl,
            'qr_svg'     => $qrSvg,
            'expires_in' => 30, // minutes
        ]);
    }

    /**
     * Polling desktop : retourne les items scannés depuis le smartphone.
     */
    public function poll($token)
    {
        $session = ScanSession::where('token', $token)
            ->where('user_id', Auth::id())
            ->first();

        if (!$session) {
            return response()->json(['status' => 'not_found', 'items' => []], 404);
        }

        if ($session->isExpired()) {
            $session->update(['status' => 'closed']);
            return response()->json(['status' => 'expired', 'items' => []]);
        }

        return response()->json([
            'status'     => $session->status,
            'items'      => $session->scanned_items ?? [],
            'expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Fermer une session de scan (depuis le desktop).
     */
    public function close($token)
    {
        ScanSession::where('token', $token)
            ->where('user_id', Auth::id())
            ->update(['status' => 'closed']);

        return response()->json(['success' => true]);
    }

    // ── Page mobile (sans authentification) ──────────────────────────────────

    /**
     * Afficher la page de scan mobile.
     * Accessible sans connexion, via le token de session.
     */
    public function mobileShow($token)
    {
        $session = ScanSession::where('token', $token)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return Inertia::render('Scanner/MobileExpired');
        }

        return Inertia::render('Scanner/Mobile', [
            'token'        => $token,
            'scannedItems' => $session->scanned_items ?? [],
        ]);
    }

    /**
     * Réceptionner un scan depuis le smartphone.
     * Accessible sans connexion, via le token de session.
     */
    public function mobileSubmit(Request $request, $token)
    {
        $session = ScanSession::where('token', $token)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session expirée ou invalide.'], 404);
        }

        $code = trim($request->input('code', ''));

        if (empty($code)) {
            return response()->json(['error' => 'Code vide.'], 422);
        }

        // Rechercher l'équipement par asset_tag
        $asset = Asset::where('asset_tag', $code)->first();

        // Fallback : URL QR code contenant l'ID ou l'asset_tag
        if (!$asset && preg_match('/\/assets\/(\d+)/', $code, $m)) {
            $asset = Asset::find($m[1]);
        }

        if (!$asset) {
            return response()->json(['error' => "Équipement « $code » introuvable."], 404);
        }

        // Vérifier si déjà scanné dans cette session
        $items = $session->scanned_items ?? [];
        $alreadyScanned = collect($items)->contains('id', $asset->id);

        if ($alreadyScanned) {
            return response()->json([
                'warning' => 'Cet équipement a déjà été scanné dans cette session.',
                'asset'   => [
                    'id'        => $asset->id,
                    'name'      => $asset->name,
                    'asset_tag' => $asset->asset_tag,
                ],
            ]);
        }

        // Ajouter à la liste des scans
        $imageUrl = $asset->image
            ? (str_starts_with($asset->image, 'http') ? $asset->image : Storage::url($asset->image))
            : null;

        $items[] = [
            'id'         => $asset->id,
            'name'       => $asset->name,
            'asset_tag'  => $asset->asset_tag,
            'status'     => $asset->status,
            'image_url'  => $imageUrl,
            'scanned_at' => now()->toIso8601String(),
        ];

        $session->update(['scanned_items' => $items]);

        return response()->json([
            'success' => true,
            'asset'   => [
                'id'        => $asset->id,
                'name'      => $asset->name,
                'asset_tag' => $asset->asset_tag,
                'status'    => $asset->status,
                'image_url' => $imageUrl,
            ],
            'total' => count($items),
        ]);
    }
}
