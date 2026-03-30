<?php

namespace App\Http\Controllers;

use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group')->map(fn($group) =>
            $group->pluck('value', 'key')
        );

        $logoLycee = Setting::get('logo_lycee');
        $logoBts   = Setting::get('logo_bts');

        return Inertia::render('Admin/Settings', [
            'settings' => $settings,
            'logoUrls' => [
                'lycee' => $logoLycee ? Storage::disk('public')->url($logoLycee) : null,
                'bts'   => $logoBts   ? Storage::disk('public')->url($logoBts)   : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        // Map of known keys to their groups
        $groupMap = [
            'school_name'                 => 'general',
            'school_address'              => 'general',
            'filter_by_reservable'        => 'visibility',
            'tab_categories'              => 'tabs',
            'tab_locations'               => 'tabs',
            'tab_manufacturers'           => 'tabs',
            'max_reservation_days'        => 'reservations',
            'max_assets_per_reservation'  => 'reservations',
            'auto_approve'                => 'reservations',
            'notify_admin_new_reservation'=> 'emails',
            'notify_user_approved'        => 'emails',
            'send_checkout_summary'       => 'emails',
            'exam_mode'                   => 'exam',
            'exam_advance_hours'          => 'exam',
            'exam_process_start'          => 'exam',
            'exam_process_end'            => 'exam',
            'return_reminder_enabled'     => 'reminders',
            'return_reminder_minutes'     => 'reminders',
            // Annulation automatique
            'auto_cancel_enabled'         => 'reservations',
            'auto_cancel_hours'           => 'reservations',
            // Fiche imprimable
            'print_show_thumbnails'       => 'print',
            'print_show_notes'            => 'print',
            'print_show_category'         => 'print',
            'print_show_asset_tag'        => 'print',
            'print_show_form'             => 'print',
            'print_proviseur_label'       => 'print',
            // Visibilité calendrier par rôle
            'calendar_visibility_professeur' => 'calendar',
            'calendar_visibility_etudiant'   => 'calendar',
            'calendar_visibility_personnel'  => 'calendar',
        ];

        foreach ($request->all() as $key => $value) {
            if (!is_string($key) || in_array($key, ['_token', '_method'])) continue;

            $group = $groupMap[$key] ?? (str_starts_with($key, 'sounds.') ? 'sounds' : 'general');

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group]
            );
        }

        return back()->with('success', 'Paramètres sauvegardés.');
    }

    /**
     * Envoyer un email de test à l'administrateur connecté.
     */
    public function testEmail(Request $request)
    {
        $admin = $request->user();

        try {
            Mail::to($admin->email)->send(new TestMail());
            return back()->with('success', "Email de test envoyé à {$admin->email}. Vérifiez votre boîte de réception.");
        } catch (\Exception $e) {
            return back()->withErrors(['email_test' => 'Échec de l\'envoi : ' . $e->getMessage()]);
        }
    }

    /**
     * Importer un logo (lycée ou BTS).
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'type' => 'required|in:lycee,bts',
            'logo' => 'required|image|mimes:jpeg,jpg,png,gif,svg,webp|max:2048',
        ]);

        $type = $request->input('type');

        // Supprimer l'ancien logo s'il existe
        $oldPath = Setting::get("logo_{$type}");
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        // Stocker le nouveau logo
        $path = $request->file('logo')->store('logos', 'public');
        Setting::updateOrCreate(
            ['key' => "logo_{$type}"],
            ['value' => $path, 'group' => 'general']
        );

        return back()->with('success', 'Logo mis à jour avec succès.');
    }

    /**
     * Supprimer un logo.
     */
    public function deleteLogo(string $type)
    {
        if (!in_array($type, ['lycee', 'bts'])) {
            abort(422);
        }

        $path = Setting::get("logo_{$type}");
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        Setting::updateOrCreate(
            ['key' => "logo_{$type}"],
            ['value' => '', 'group' => 'general']
        );

        return back()->with('success', 'Logo supprimé.');
    }
}
