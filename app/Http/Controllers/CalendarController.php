<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // L'admin voit toujours tout
        $canSeeAll = $user->hasRole('admin');

        if (!$canSeeAll) {
            foreach (['professeur', 'etudiant', 'personnel'] as $role) {
                if ($user->hasRole($role)) {
                    $canSeeAll = Setting::get("calendar_visibility_{$role}", 'false') === 'true';
                    break;
                }
            }
        }

        return Inertia::render('Calendar/Index', [
            'canSeeAllEvents' => $canSeeAll,
        ]);
    }
}
