<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Calendar via Service Account (JWT).
 * Pas besoin de redirect URI — compatible avec les serveurs sans domaine public.
 *
 * Mise en place côté Google Cloud :
 *  1. Créer un projet → API & Services → Activer "Google Calendar API"
 *  2. Identifiants → Créer un compte de service → télécharger la clé JSON
 *  3. Dans Google Calendar, partager le calendrier cible avec l'email du compte
 *     de service (permission "Apporter des modifications aux événements")
 *  4. Coller le contenu du JSON dans Paramètres → Google Calendar
 *  5. Saisir l'ID du calendrier cible (Paramètres du calendrier → Intégrer)
 */
class GoogleCalendarService
{
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    // ── Configuration ─────────────────────────────────────────────────────────

    public static function isEnabled(): bool
    {
        return Setting::get('google_calendar_enabled', 'false') === 'true';
    }

    /** Vrai si un compte de service valide (email + clé privée) est configuré. */
    public static function isConnected(): bool
    {
        $json = Setting::get('google_service_account_json', '');
        if (!$json) return false;
        $data = json_decode($json, true);
        return isset($data['client_email'], $data['private_key'])
            && !empty($data['client_email'])
            && !empty($data['private_key']);
    }

    /** Renvoie l'email du compte de service configuré, ou null. */
    public static function getConnectedEmail(): ?string
    {
        $json = Setting::get('google_service_account_json', '');
        if (!$json) return null;
        $data = json_decode($json, true);
        return $data['client_email'] ?? null;
    }

    /** Sync des réservations activée ? */
    public static function syncReservations(): bool
    {
        return Setting::get('google_calendar_sync_reservations', 'true') === 'true';
    }

    /** Supprime la clé du compte de service et le token en cache. */
    public static function disconnect(): void
    {
        Setting::updateOrCreate(
            ['key' => 'google_service_account_json'],
            ['value' => null, 'group' => 'google_calendar']
        );
        Setting::updateOrCreate(
            ['key' => 'google_calendar_token'],
            ['value' => null, 'group' => 'google_calendar']
        );
    }

    // ── URL du calendrier cible ───────────────────────────────────────────────

    private static function eventsUrl(): string
    {
        $calId = Setting::get('google_calendar_id', 'primary') ?: 'primary';
        return 'https://www.googleapis.com/calendar/v3/calendars/'
            . urlencode($calId) . '/events';
    }

    // ── JWT & Token (Service Account) ─────────────────────────────────────────

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Construit un JWT signé RS256 pour le compte de service.
     */
    private static function buildJWT(string $clientEmail, string $privateKey): string
    {
        $now = time();
        $header  = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = self::base64url(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $sigInput = $header . '.' . $payload;
        openssl_sign($sigInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return $sigInput . '.' . self::base64url($signature);
    }

    /**
     * Retourne une instance Http pré-configurée avec le bon CA bundle SSL.
     */
    private static function http(): \Illuminate\Http\Client\PendingRequest
    {
        // Sur Windows Server, cURL ne trouve pas les CA système.
        // On force le bundle Mozilla téléchargé localement.
        $caBundle = storage_path('app/cacert.pem');
        if (file_exists($caBundle)) {
            return Http::withOptions(['verify' => $caBundle]);
        }
        // Fallback : désactive la vérification (accepté pour appels sortants vers Google)
        return Http::withOptions(['verify' => false]);
    }

    /**
     * Retourne un access token valide (depuis cache ou via JWT).
     */
    public static function getAccessToken(): ?string
    {
        // Token en cache (buffer 5 min)
        $cached = Setting::get('google_calendar_token', '');
        if ($cached) {
            $token = json_decode($cached, true);
            if (!empty($token['access_token'])
                && ($token['expires_at'] ?? 0) > time() + 300
            ) {
                return $token['access_token'];
            }
        }

        // Charger les credentials du compte de service
        $json = Setting::get('google_service_account_json', '');
        if (!$json) return null;
        $keyData = json_decode($json, true);
        if (!isset($keyData['client_email'], $keyData['private_key'])) return null;

        try {
            $jwt = self::buildJWT($keyData['client_email'], $keyData['private_key']);

            $response = self::http()->asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                Log::warning('Google Calendar: token request failed', $response->json());
                return null;
            }

            $data = $response->json();
            Setting::updateOrCreate(
                ['key' => 'google_calendar_token'],
                ['value' => json_encode([
                    'access_token' => $data['access_token'],
                    'expires_at'   => time() + ($data['expires_in'] ?? 3600),
                ]), 'group' => 'google_calendar']
            );
            return $data['access_token'];
        } catch (\Exception $e) {
            Log::error('Google Calendar: JWT/token error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Event CRUD ────────────────────────────────────────────────────────────

    /**
     * Formate le champ "reminders" pour l'API Google Calendar.
     * $reminders = [['method' => 'popup', 'minutes' => 30], ...]
     */
    private static function buildRemindersPayload(array $reminders): array
    {
        $overrides = array_values(array_filter($reminders, fn($r) =>
            isset($r['method'], $r['minutes'])
            && in_array($r['method'], ['popup', 'email'])
            && is_numeric($r['minutes'])
            && $r['minutes'] >= 0
        ));

        if (empty($overrides)) {
            return ['useDefault' => true];
        }

        return [
            'useDefault' => false,
            'overrides'  => array_map(fn($r) => [
                'method'  => $r['method'],
                'minutes' => (int) $r['minutes'],
            ], $overrides),
        ];
    }

    /**
     * Crée un événement datetime (réservation). Retourne l'event_id ou null.
     */
    public static function createDateTimeEvent(
        string $title,
        string $description,
        Carbon $start,
        Carbon $end,
        array  $calendarReminders = []
    ): ?string {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return null;

        try {
            $tz = config('app.timezone', 'UTC');
            $response = self::http()->withToken($accessToken)->post(self::eventsUrl(), [
                'summary'     => $title,
                'description' => $description,
                'start'       => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $tz],
                'end'         => ['dateTime' => $end->toRfc3339String(),   'timeZone' => $tz],
                'reminders'   => self::buildRemindersPayload($calendarReminders),
            ]);

            if ($response->failed()) {
                Log::warning('Google Calendar: createDateTimeEvent failed', $response->json());
                return null;
            }
            return $response->json('id');
        } catch (\Exception $e) {
            Log::error('Google Calendar: createDateTimeEvent error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Crée un événement all-day (tâche todo avec due_date). Retourne l'event_id ou null.
     */
    public static function createAllDayEvent(
        string $title,
        string $description,
        string $date, // 'Y-m-d'
        array  $calendarReminders = []
    ): ?string {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return null;

        try {
            $endDate = Carbon::parse($date)->addDay()->format('Y-m-d');
            $response = self::http()->withToken($accessToken)->post(self::eventsUrl(), [
                'summary'     => $title,
                'description' => $description,
                'start'       => ['date' => $date],
                'end'         => ['date' => $endDate],
                'reminders'   => self::buildRemindersPayload($calendarReminders),
            ]);

            if ($response->failed()) {
                Log::warning('Google Calendar: createAllDayEvent failed', $response->json());
                return null;
            }
            return $response->json('id');
        } catch (\Exception $e) {
            Log::error('Google Calendar: createAllDayEvent error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Met à jour un événement datetime existant (PATCH = mise à jour partielle).
     */
    public static function updateDateTimeEvent(
        string $eventId,
        string $title,
        string $description,
        Carbon $start,
        Carbon $end,
        array  $calendarReminders = []
    ): bool {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return false;

        try {
            $tz = config('app.timezone', 'UTC');
            $response = self::http()->withToken($accessToken)->patch(self::eventsUrl() . '/' . $eventId, [
                'summary'     => $title,
                'description' => $description,
                'start'       => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $tz],
                'end'         => ['dateTime' => $end->toRfc3339String(),   'timeZone' => $tz],
                'reminders'   => self::buildRemindersPayload($calendarReminders),
            ]);
            if ($response->failed()) {
                Log::warning('Google Calendar: updateDateTimeEvent failed', ['status' => $response->status(), 'body' => $response->json()]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar: updateDateTimeEvent error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Met à jour un événement all-day existant (PATCH = mise à jour partielle).
     */
    public static function updateAllDayEvent(
        string $eventId,
        string $title,
        string $description,
        string $date,
        array  $calendarReminders = []
    ): bool {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return false;

        try {
            $endDate = Carbon::parse($date)->addDay()->format('Y-m-d');
            $response = self::http()->withToken($accessToken)->patch(self::eventsUrl() . '/' . $eventId, [
                'summary'     => $title,
                'description' => $description,
                'start'       => ['date' => $date],
                'end'         => ['date' => $endDate],
                'reminders'   => self::buildRemindersPayload($calendarReminders),
            ]);
            if ($response->failed()) {
                Log::warning('Google Calendar: updateAllDayEvent failed', ['status' => $response->status(), 'body' => $response->json()]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar: updateAllDayEvent error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Met à jour UNIQUEMENT les alertes d'un événement existant via PATCH ciblé.
     * Plus rapide et plus fiable que de re-syncer tout l'événement.
     */
    public static function patchEventReminders(string $eventId, array $calendarReminders): bool
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return false;

        try {
            $response = self::http()->withToken($accessToken)->patch(self::eventsUrl() . '/' . $eventId, [
                'reminders' => self::buildRemindersPayload($calendarReminders),
            ]);
            if ($response->failed()) {
                Log::warning('Google Calendar: patchEventReminders failed', ['status' => $response->status(), 'body' => $response->json()]);
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar: patchEventReminders error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Supprime un événement (silencieux si déjà supprimé).
     */
    public static function deleteEvent(string $eventId): bool
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return false;

        try {
            $response = self::http()->withToken($accessToken)->delete(self::eventsUrl() . '/' . $eventId);
            return $response->successful()
                || $response->status() === 404
                || $response->status() === 410;
        } catch (\Exception $e) {
            Log::error('Google Calendar: deleteEvent error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ── Domain helpers ────────────────────────────────────────────────────────

    /**
     * Crée ou met à jour l'événement Google Calendar pour une réservation.
     */
    public static function syncReservation(\App\Models\Reservation $reservation): ?string
    {
        if (!self::isEnabled() || !self::isConnected() || !self::syncReservations()) return null;

        $assets = $reservation->assets->map(fn($a) => $a->name . ' [' . $a->asset_tag . ']')->implode("\n");
        $description = "Emprunteur : {$reservation->user->name}\n"
            . ($reservation->notes ? "Notes : {$reservation->notes}\n" : '')
            . ($assets ? "\nÉquipements :\n{$assets}" : '');

        $title = '📋 ' . $reservation->title;
        $start = Carbon::parse($reservation->start_date);
        $end   = Carbon::parse($reservation->end_date);

        if ($reservation->google_event_id) {
            self::updateDateTimeEvent($reservation->google_event_id, $title, $description, $start, $end);
            return $reservation->google_event_id;
        }

        return self::createDateTimeEvent($title, $description, $start, $end);
    }

    /**
     * Crée ou met à jour l'événement Google Calendar pour une tâche todo.
     * - Avec start_time → événement datetime (plage horaire précise)
     * - Sans start_time → événement toute la journée
     */
    public static function syncTask(\App\Models\TodoTask $task): ?string
    {
        if (!self::isEnabled() || !self::isConnected()) return null;
        if (!$task->due_date) return $task->google_event_id;

        $description = ($task->description ?? '')
            . ($task->project ? "\nProjet : " . $task->project->name : '');
        if ($task->start_time) {
            $endTime = $task->end_time ?? null;
            $description .= "\nPlage : " . $task->start_time
                . ($endTime ? " – {$endTime}" : ' (heure de début)');
        }

        $title = '✅ ' . $task->title;
        $date  = $task->due_date->format('Y-m-d');

        $calReminders = $task->calendar_reminders ?? [];

        // ── Événement avec plage horaire ──────────────────────────────────
        if ($task->start_time) {
            $tz    = config('app.timezone', 'UTC');
            $start = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $task->start_time, $tz);

            if ($task->end_time) {
                $end = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $task->end_time, $tz);
                // Si fin <= début (ex: 23:00 – 01:00), passer au lendemain
                if ($end->lte($start)) $end->addDay();
            } else {
                $end = (clone $start)->addHour(); // défaut : +1h
            }

            if ($task->google_event_id) {
                $ok = self::updateDateTimeEvent($task->google_event_id, $title, $description, $start, $end, $calReminders);
                if (!$ok) throw new \RuntimeException('Échec de la mise à jour de l\'événement Google Calendar (datetime).');
                return $task->google_event_id;
            }
            return self::createDateTimeEvent($title, $description, $start, $end, $calReminders);
        }

        // ── Événement toute la journée ────────────────────────────────────
        if ($task->google_event_id) {
            $ok = self::updateAllDayEvent($task->google_event_id, $title, $description, $date, $calReminders);
            if (!$ok) throw new \RuntimeException('Échec de la mise à jour de l\'événement Google Calendar (all-day).');
            return $task->google_event_id;
        }
        return self::createAllDayEvent($title, $description, $date, $calReminders);
    }

    // ── Diagnostic pas-à-pas ─────────────────────────────────────────────────

    /**
     * Diagnostic détaillé : teste chaque étape et retourne ['ok'=>bool, 'message'=>string].
     */
    public static function diagnose(): array
    {
        // Étape 1 — JSON présent ?
        $json = Setting::get('google_service_account_json', '');
        if (!$json) {
            return ['ok' => false, 'message' => 'Étape 1 : Aucune clé JSON configurée dans les paramètres.'];
        }

        // Étape 2 — JSON valide ?
        $keyData = json_decode($json, true);
        if (!$keyData) {
            return ['ok' => false, 'message' => 'Étape 2 : JSON invalide — ' . json_last_error_msg() . '. Vérifiez que vous avez copié l\'intégralité du fichier.'];
        }
        if (empty($keyData['client_email'])) {
            return ['ok' => false, 'message' => 'Étape 2 : Champ "client_email" manquant dans le JSON.'];
        }
        if (empty($keyData['private_key'])) {
            return ['ok' => false, 'message' => 'Étape 2 : Champ "private_key" manquant dans le JSON.'];
        }

        // Étape 3 — OpenSSL peut signer ?
        $privateKey = $keyData['private_key'];
        $signed = openssl_sign('ping', $sig, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            $err = '';
            while ($e = openssl_error_string()) $err .= $e . ' ';
            return ['ok' => false, 'message' => 'Étape 3 : Impossible de signer avec la clé privée (OpenSSL). ' . trim($err)];
        }

        // Étape 4 — Échange JWT → access token
        try {
            $jwt = self::buildJWT($keyData['client_email'], $privateKey);
            $response = self::http()->timeout(15)->asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if ($response->failed()) {
                $body = $response->json();
                $detail = $body['error_description'] ?? ($body['error'] ?? json_encode($body));
                return ['ok' => false, 'message' => "Étape 4 : Google a refusé le token — {$detail}. Vérifiez que l'API Google Calendar est activée dans le projet Cloud et que le compte de service est correct."];
            }

            $data = $response->json();
            // Mise en cache
            Setting::updateOrCreate(
                ['key' => 'google_calendar_token'],
                ['value' => json_encode([
                    'access_token' => $data['access_token'],
                    'expires_at'   => time() + ($data['expires_in'] ?? 3600),
                ]), 'group' => 'google_calendar']
            );

            return ['ok' => true, 'message' => 'Connexion réussie (' . $keyData['client_email'] . ').'];
        } catch (\Exception $e) {
            return ['ok' => false, 'message' => 'Étape 4 : Erreur réseau — ' . $e->getMessage()];
        }
    }
}
