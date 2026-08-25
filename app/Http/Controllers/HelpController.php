<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HelpController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $role = $user->hasRole('admin') ? 'admin'
              : ($user->hasRole('professeur') ? 'professeur' : 'etudiant');

        return Inertia::render('Help/Index', [
            'userRole' => $role,
        ]);
    }
}
