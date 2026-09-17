<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                    'department' => $request->user()->department,
                    'roles'          => $request->user()->getRoleNames(),
                    'is_admin'       => $request->user()->hasRole('admin'),
                    'is_professeur'  => $request->user()->hasRole('professeur'),
                    'role_label'     => $request->user()->hasRole('admin')     ? 'Administrateur'
                        : ($request->user()->hasRole('professeur') ? 'Professeur'
                        : ($request->user()->hasRole('personnel')  ? 'Personnel'
                        : 'Étudiant')),
                    'avatar_url'     => $request->user()->avatar
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($request->user()->avatar)
                        : null,
                ] : null,
            ],
            'tabSettings' => fn () => $request->user() ? [
                'categories'    => \App\Models\Setting::get('tab_categories', 'true') === 'true',
                'locations'     => \App\Models\Setting::get('tab_locations', 'true') === 'true',
                'manufacturers' => \App\Models\Setting::get('tab_manufacturers', 'true') === 'true',
                // Accès page Commandes par rôle
                'section_procurement_professeur_read' => \App\Models\Setting::get('section_procurement_professeur_read', 'false'),
                'section_procurement_personnel_read'  => \App\Models\Setting::get('section_procurement_personnel_read', 'false'),
                'section_procurement_etudiant_read'   => \App\Models\Setting::get('section_procurement_etudiant_read', 'false'),
            ] : null,
            'unreadNotifications' => fn () => $request->user() ? $request->user()->unreadNotifications()->count() : 0,
            'pendingIpChangeRequests' => fn () => $request->user() && $request->user()->hasRole('admin')
                ? \App\Models\ComputerIpChangeRequest::where('status', 'pending')->count()
                : 0,
            'flash' => [
                'success'        => fn () => $request->session()->get('success'),
                'error'          => fn () => $request->session()->get('error'),
                'warning'        => fn () => $request->session()->get('warning'),
                'import_results' => fn () => $request->session()->get('import_results'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            // Indique si l'utilisateur est superviseur d'au moins un groupe
            'isSupervisor' => fn () => $request->user()
                ? $request->user()->isSupervisor()
                : false,

            // Préférences sonores (partagées pour useSound.js)
            'soundSettings' => fn () => $request->user() ? [
                'enabled'              => \App\Models\Setting::get('sounds.enabled', 'true'),
                'volume'               => \App\Models\Setting::get('sounds.volume', '80'),
                'notification_general' => \App\Models\Setting::get('sounds.notification_general', 'chime'),
                'notification_kit'     => \App\Models\Setting::get('sounds.notification_kit', 'ding'),
                'scan_success'         => \App\Models\Setting::get('sounds.scan_success', 'beep'),
                'scan_error'           => \App\Models\Setting::get('sounds.scan_error', 'buzz'),
            ] : null,

            // Logos lycée / BTS (partagés globalement pour AppLayout)
            'appLogos' => fn () => [
                'lycee'       => ($lLycee = \App\Models\Setting::get('logo_lycee')) && $lLycee
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($lLycee) : null,
                'bts'         => ($lBts = \App\Models\Setting::get('logo_bts')) && $lBts
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($lBts) : null,
                'school_name' => \App\Models\Setting::get('school_name', config('app.name')),
            ],

            // To-Do List : activée pour tous ou seulement les admins
            'todoEnabled' => fn () => $request->user()
                ? ($request->user()->hasRole('admin') || \App\Models\Setting::get('todo_enabled', 'false') === 'true')
                : false,

            // Statuts d'équipements partagés globalement (mis en cache)
            'assetStatuses' => fn () => $request->user()
                ? \App\Models\AssetStatus::allCached()->map(fn($s) => [
                    'key'                => $s->key,
                    'label'              => $s->label,
                    'color'              => $s->color,
                    'is_system'          => $s->is_system,
                    'blocks_reservation' => $s->blocks_reservation,
                    'is_active'          => $s->is_active,
                ])->keyBy('key')
                : collect(),
        ];
    }
}
