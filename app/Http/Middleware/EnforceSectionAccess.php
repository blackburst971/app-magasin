<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class EnforceSectionAccess
{
    /**
     * Route name prefixes → section key.
     * Admin users always bypass this check.
     */
    const ROUTE_TO_SECTION = [
        'calendar.'          => 'calendar',
        'rooms.'             => 'rooms',
        'room-reservations.' => 'rooms',
        'kits.'              => 'kits',
        'reservations.'      => 'reservations',
        'consommables.'      => 'consommables',
        'manufacturers.'     => 'manufacturers',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Admins always have full access
        if (!$user || $user->hasRole('admin')) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        // Find which section this route belongs to
        $section = null;
        foreach (self::ROUTE_TO_SECTION as $prefix => $key) {
            if (str_starts_with($routeName, $prefix)) {
                $section = $key;
                break;
            }
        }

        // Route not tied to a controlled section
        if (!$section) {
            return $next($request);
        }

        // Determine user role (most privileged wins)
        $role = $user->hasRole('professeur') ? 'professeur'
              : ($user->hasRole('personnel')  ? 'personnel' : 'etudiant');

        $enabled = Setting::get("section_{$section}_{$role}", 'true') === 'true';

        if (!$enabled) {
            // Inertia request → redirect with flash error
            if ($request->header('X-Inertia')) {
                return redirect()->route('dashboard')
                    ->with('error', 'Cette section n\'est pas accessible avec votre rôle.');
            }
            abort(403, 'Cette section n\'est pas accessible.');
        }

        return $next($request);
    }
}
