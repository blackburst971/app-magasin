<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        // Cherche tous les comptes ayant cet email
        $users = User::where('email', $request->email)->get();

        if ($users->count() > 1) {
            // Filtre ceux dont le mot de passe correspond
            $matching = $users->filter(fn($u) => Hash::check($request->password, $u->password));

            if ($matching->isEmpty()) {
                RateLimiter::hit($request->throttleKey());
                throw ValidationException::withMessages(['email' => trans('auth.failed')]);
            }

            RateLimiter::clear($request->throttleKey());

            if ($matching->count() === 1) {
                // Un seul compte correspond → connexion directe
                Auth::login($matching->first(), $request->boolean('remember'));
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard', absolute: false));
            }

            // Plusieurs comptes correspondent → page de sélection
            $request->session()->put('login.candidates', $matching->pluck('id')->toArray());
            $request->session()->put('login.remember', $request->boolean('remember'));

            return redirect()->route('login.choose');
        }

        // Flux normal (email unique)
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Affiche la page de sélection de compte (email partagé).
     */
    public function chooseAccount(Request $request): Response|RedirectResponse
    {
        $candidateIds = $request->session()->get('login.candidates');

        if (empty($candidateIds)) {
            return redirect()->route('login');
        }

        $accounts = User::whereIn('id', $candidateIds)
            ->get()
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'department' => $u->department,
                'role_label' => $u->hasRole('admin')      ? 'Administrateur'
                    : ($u->hasRole('professeur') ? 'Professeur'
                    : ($u->hasRole('personnel')  ? 'Personnel'
                    : 'Étudiant')),
            ]);

        return Inertia::render('Auth/ChooseAccount', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Connecte l'utilisateur sélectionné sur la page de choix.
     */
    public function selectAccount(Request $request): RedirectResponse
    {
        $candidateIds = $request->session()->get('login.candidates');

        if (empty($candidateIds)) {
            return redirect()->route('login');
        }

        $request->validate(['user_id' => ['required', 'integer']]);

        // Sécurité : le user_id doit être parmi les candidats de la session
        if (!in_array((int) $request->user_id, $candidateIds)) {
            return redirect()->route('login')->withErrors(['email' => 'Sélection invalide.']);
        }

        $user = User::findOrFail($request->user_id);
        $remember = $request->session()->get('login.remember', false);

        $request->session()->forget(['login.candidates', 'login.remember']);
        $request->session()->regenerate();

        Auth::login($user, $remember);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
