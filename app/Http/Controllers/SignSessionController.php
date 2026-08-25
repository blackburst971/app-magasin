<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\SignSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SignSessionController extends Controller
{
    /**
     * Créer une session de signature jumelée.
     * Retourne le token, l'URL mobile et le QR code SVG.
     * Accessible par les admins et les superviseurs authentifiés.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'reservation_id' => 'required|exists:reservations,id',
            'supervisor_id'  => 'required|exists:users,id',
            'purpose'        => 'in:supervisor,admin_proxy',
        ]);

        $reservation = Reservation::with(['user', 'assets'])->findOrFail($data['reservation_id']);
        $supervisor  = User::findOrFail($data['supervisor_id']);
        $purpose     = $data['purpose'] ?? 'supervisor';

        // Fermer les sessions actives existantes pour cette réservation
        SignSession::where('reservation_id', $reservation->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        $token = Str::random(40);

        SignSession::create([
            'token'          => $token,
            'reservation_id' => $reservation->id,
            'supervisor_id'  => $supervisor->id,
            'purpose'        => $purpose,
            'status'         => 'active',
            'expires_at'     => now()->addMinutes(15),
            'context'        => [
                'reservation_title' => $reservation->title,
                'student_name'      => $reservation->user->name,
                'start_date'        => $reservation->start_date->format('d/m/Y H:i'),
                'end_date'          => $reservation->end_date->format('d/m/Y H:i'),
                'assets'            => $reservation->assets->map(fn($a) => $a->name)->join(', '),
                'supervisor_name'   => $supervisor->name,
                'purpose'           => $purpose,
            ],
        ]);

        $mobileUrl = url('/sign/mobile/' . $token);
        $qrSvg = (string) QrCode::format('svg')->size(280)->generate($mobileUrl);
        $qrSvg = preg_replace('/<\?xml[^>]*\?>/', '', $qrSvg);

        return response()->json([
            'token'      => $token,
            'mobile_url' => $mobileUrl,
            'qr_svg'     => $qrSvg,
            'expires_in' => 15, // minutes
        ]);
    }

    /**
     * Polling depuis le desktop : vérifie si la signature a été soumise.
     */
    public function poll($token)
    {
        $session = SignSession::where('token', $token)->first();

        if (!$session) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($session->status === 'active' && $session->isExpired()) {
            $session->update(['status' => 'expired']);
            return response()->json(['status' => 'expired', 'signature' => null]);
        }

        return response()->json([
            'status'    => $session->status,
            'signature' => $session->signature,
        ]);
    }

    // ── Pages mobiles (sans authentification, accès par token) ──────────────

    /**
     * Afficher la page de signature mobile.
     */
    public function mobileShow($token)
    {
        $session = SignSession::where('token', $token)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return Inertia::render('Sign/MobileExpired');
        }

        return Inertia::render('Sign/Mobile', [
            'token'   => $token,
            'context' => $session->context,
        ]);
    }

    /**
     * Recevoir la signature depuis le mobile.
     */
    public function mobileSubmit(Request $request, $token)
    {
        $session = SignSession::where('token', $token)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json(['error' => 'Session expirée ou invalide.'], 404);
        }

        $request->validate([
            'signature' => 'required|string|min:10',
        ], [
            'signature.required' => 'La signature est obligatoire.',
            'signature.min'      => 'La signature est invalide.',
        ]);

        $session->update([
            'signature' => $request->signature,
            'status'    => 'completed',
        ]);

        return response()->json(['success' => true]);
    }
}
