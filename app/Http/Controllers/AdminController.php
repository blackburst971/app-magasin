<?php

namespace App\Http\Controllers;

use App\Mail\UserCredentialsMail;
use App\Models\User;
use App\Models\KitMissingItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use League\Csv\Reader;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    public function users()
    {
        $allUsers = User::with(['roles', 'supervisor', 'supervisors'])->orderBy('name')->get();

        return Inertia::render('Admin/Users', [
            'users' => $allUsers->map(fn($u) => [
                'id'              => $u->id,
                'name'            => $u->name,
                'email'           => $u->email,
                'department'      => $u->department,
                'phone'           => $u->phone,
                'supervisor_id'   => $u->supervisor_id,
                'supervisor_name' => $u->supervisor?->name,
                'supervisors'     => $u->supervisors->map(fn($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ]),
                'roles'           => $u->getRoleNames(),
                'created_at'      => $u->created_at->format('d/m/Y'),
                'avatar_url'      => $u->avatar ? Storage::disk('public')->url($u->avatar) : null,
            ]),
            // Liste plate id+name pour les dropdowns superviseur
            'allSupervisors' => $allUsers->map(fn($u) => ['id' => $u->id, 'name' => $u->name]),
            'roles' => Role::all(['id', 'name']),
        ]);
    }

    public function storeUser(Request $request)
    {
        // L'admin peut créer un utilisateur avec un email déjà existant (pour tests, doublons volontaires)
        $allowDuplicate = (bool) $request->input('allow_duplicate_email', false);
        $emailRule = $allowDuplicate ? 'required|email' : 'required|email|unique:users';

        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => $emailRule,
            'password'              => 'required|string|min:8',
            'department'            => 'nullable|string|max:255',
            'phone'                 => 'nullable|string|max:20',
            'role'                  => 'required|in:admin,professeur,personnel,user',
            'supervisor_id'         => 'nullable|exists:users,id',
            'send_credentials'      => 'nullable|boolean',
            'allow_duplicate_email' => 'nullable|boolean',
        ]);

        $plainPassword = $data['password'];

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($plainPassword),
            'department'        => $data['department'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'supervisor_id'     => $data['supervisor_id'] ?? null,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($data['role']);

        if (!empty($data['send_credentials'])) {
            try {
                Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));
            } catch (\Exception $e) {
                // Ne pas bloquer la création si l'email échoue
            }
        }

        return back()->with('success', 'Utilisateur créé.' . (!empty($data['send_credentials']) ? ' Email de bienvenue envoyé.' : ''));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => "required|email|unique:users,email,{$user->id}",
            'department'    => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:20',
            'role'          => 'nullable|in:admin,professeur,personnel,user',
            'supervisor_id' => [
                'nullable',
                'exists:users,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value && (int) $value === $user->id) {
                        $fail('Un utilisateur ne peut pas être son propre responsable.');
                    }
                },
            ],
        ]);

        $role = $data['role'] ?? null;
        unset($data['role']);

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        if ($request->password) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === request()->user()->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        $user->delete();
        return back()->with('success', 'Utilisateur supprimé.');
    }

    public function bulkUpdateUsers(Request $request)
    {
        $request->validate([
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer|exists:users,id',
            'apply_role'      => 'nullable|boolean',
            'role'            => 'nullable|in:admin,professeur,personnel,user',
            'apply_supervisor'=> 'nullable|boolean',
            'supervisor_id'   => 'nullable|integer|exists:users,id',
            'apply_department'=> 'nullable|boolean',
            'department'      => 'nullable|string|max:255',
        ]);

        $currentUserId = request()->user()->id;
        $ids = array_values(array_filter($request->ids, fn($id) => (int)$id !== $currentUserId));

        if (empty($ids)) {
            return back()->with('error', 'Aucun utilisateur modifiable dans la sélection.');
        }

        $users = User::whereIn('id', $ids)->get();
        $count = 0;

        foreach ($users as $user) {
            $updateData = [];

            if ($request->boolean('apply_role') && $request->role) {
                $user->syncRoles([$request->role]);
            }

            if ($request->boolean('apply_supervisor')) {
                $supId = $request->supervisor_id ? (int)$request->supervisor_id : null;
                if ($supId !== $user->id) {
                    $updateData['supervisor_id'] = $supId;
                }
            }

            if ($request->boolean('apply_department')) {
                $updateData['department'] = $request->department ?: null;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }
            $count++;
        }

        return back()->with('success', "{$count} utilisateur(s) mis à jour.");
    }

    public function bulkDestroyUsers(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
        ]);

        $currentUserId = request()->user()->id;
        $ids = array_values(array_filter($request->ids, fn($id) => (int)$id !== $currentUserId));

        if (empty($ids)) {
            return back()->with('error', 'Impossible de supprimer votre propre compte.');
        }

        $count = User::whereIn('id', $ids)->count();
        User::whereIn('id', $ids)->delete();

        return back()->with('success', "{$count} utilisateur(s) supprimé(s).");
    }

    public function changeRole(Request $request, User $user)
    {
        $request->validate(['role' => 'required|in:admin,professeur,personnel,user']);
        $user->syncRoles([$request->role]);
        return back()->with('success', 'Rôle mis à jour.');
    }

    public function updateUserAvatar(Request $request, User $user)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpeg,jpg,png,gif,webp,avif|max:2048']);
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);
        return back()->with('success', 'Photo de profil mise à jour.');
    }

    // ── Import CSV ───────────────────────────────────────────────────────────

    /** Télécharge le modèle CSV vierge. */
    public function downloadTemplate()
    {
        $lines  = [];
        $lines[] = "name,email,password,role,department,phone";
        $lines[] = "Jean Dupont,jean.dupont@lycee.fr,MotDePasse123!,user,Terminale S,0601020304";
        $lines[] = "Marie Martin,marie.martin@lycee.fr,,professeur,Sciences,";

        return response(implode("\n", $lines), 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template_utilisateurs.csv"',
        ]);
    }

    /** Parse le CSV et retourne un aperçu JSON (sans toucher à la BDD). */
    public function importPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $csv = Reader::createFromPath($request->file('file')->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $validRoles     = ['admin', 'professeur', 'personnel', 'user'];
        $existingEmails = User::pluck('email')->flip()->toArray();
        $seenEmails     = [];
        $preview        = [];

        foreach ($csv->getRecords() as $offset => $record) {
            $name       = trim($record['name']       ?? '');
            $email      = strtolower(trim($record['email']      ?? ''));
            $role       = trim($record['role']       ?? 'user');
            $department = trim($record['department'] ?? '');
            $phone      = trim($record['phone']      ?? '');
            $password   = trim($record['password']   ?? '');

            $rowErrors = [];

            if (empty($name)) {
                $rowErrors[] = 'Nom manquant';
            }
            if (empty($email)) {
                $rowErrors[] = 'Email manquant';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Email invalide';
            } elseif (isset($existingEmails[$email])) {
                $rowErrors[] = 'Email déjà utilisé';
            } elseif (isset($seenEmails[$email])) {
                $rowErrors[] = 'Email en double dans le fichier';
            } else {
                $seenEmails[$email] = true;
            }

            if (!empty($role) && !in_array($role, $validRoles)) {
                $rowErrors[] = "Rôle invalide : « $role » (valeurs : admin, professeur, personnel, user)";
            }

            $preview[] = [
                'row'         => $offset + 2,
                'name'        => $name,
                'email'       => $email,
                'role'        => in_array($role, $validRoles) ? $role : 'user',
                'department'  => $department,
                'phone'       => $phone,
                'hasPassword' => !empty($password),
                'errors'      => $rowErrors,
            ];
        }

        return response()->json([
            'preview' => $preview,
            'total'   => count($preview),
            'valid'   => count(array_filter($preview, fn($r) => empty($r['errors']))),
        ]);
    }

    /** Importe les utilisateurs valides du CSV en BDD. */
    public function importProcess(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $csv = Reader::createFromPath($request->file('file')->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $validRoles = ['admin', 'professeur', 'personnel', 'user'];
        $imported   = [];
        $skipped    = [];

        foreach ($csv->getRecords() as $record) {
            $name       = trim($record['name']       ?? '');
            $email      = strtolower(trim($record['email']      ?? ''));
            $role       = trim($record['role']       ?? 'user');
            $department = trim($record['department'] ?? '') ?: null;
            $phone      = trim($record['phone']      ?? '') ?: null;
            $password   = trim($record['password']   ?? '');

            // Validation basique
            if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = ['email' => $email ?: '?', 'reason' => 'Données invalides'];
                continue;
            }
            if (User::where('email', $email)->exists()) {
                $skipped[] = ['email' => $email, 'reason' => 'Email déjà utilisé'];
                continue;
            }
            if (!in_array($role, $validRoles)) {
                $role = 'user';
            }

            $generatedPassword = null;
            if (empty($password)) {
                $generatedPassword = Str::random(8) . rand(10, 99) . '!';
                $password = $generatedPassword;
            }

            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make($password),
                'department'        => $department,
                'phone'             => $phone,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($role);

            $imported[] = [
                'name'               => $name,
                'email'              => $email,
                'role'               => $role,
                'generated_password' => $generatedPassword,
            ];
        }

        return back()->with('import_results', [
            'imported' => $imported,
            'skipped'  => $skipped,
        ]);
    }

    public function showUser(User $user)
    {
        $reservations = $user->reservations()->with(['assets.category', 'kit'])->latest()->get();

        $stats = [
            'total'     => $reservations->count(),
            'pending'   => $reservations->where('status', 'pending')->count(),
            'approved'  => $reservations->where('status', 'approved')->count(),
            'active'    => $reservations->where('status', 'active')->count(),
            'returned'  => $reservations->where('status', 'returned')->count(),
            'cancelled' => $reservations->whereIn('status', ['cancelled', 'rejected'])->count(),
        ];

        // Éléments de kit manquants dont cet utilisateur est responsable
        $missingItems = KitMissingItem::with(['kit', 'asset', 'reservation'])
            ->where('responsible_user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($m) => [
                'id'                   => $m->id,
                'kit'                  => $m->kit ? ['id' => $m->kit->id, 'name' => $m->kit->name] : null,
                'asset'                => ['id' => $m->asset->id, 'name' => $m->asset->name, 'asset_tag' => $m->asset->asset_tag],
                'reservation'          => $m->reservation ? ['id' => $m->reservation->id, 'title' => $m->reservation->title] : null,
                'notes'                => $m->notes,
                'regularized'          => $m->isRegularized(),
                'regularized_at'       => $m->regularized_at?->format('d/m/Y H:i'),
                'regularization_notes' => $m->regularization_notes,
                'reported_at'          => $m->created_at->format('d/m/Y H:i'),
            ]);

        // Rapports de dégradation liés aux réservations de l'utilisateur
        $damageReports = \App\Models\AdminAssetReport::with(['asset', 'reservation'])
            ->whereHas('reservation', fn($q) => $q->where('user_id', $user->id))
            ->whereIn('type', ['damage', 'malfunction'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'type'             => $r->type,
                'title'            => $r->title,
                'content'          => $r->content,
                'return_condition' => $r->return_condition,
                'asset'            => ['id' => $r->asset->id, 'name' => $r->asset->name, 'asset_tag' => $r->asset->asset_tag],
                'reservation'      => $r->reservation ? ['id' => $r->reservation->id, 'title' => $r->reservation->title] : null,
                'created_at'       => $r->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/UserDetail', [
            'user' => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'department'         => $user->department,
                'phone'              => $user->phone,
                'roles'              => $user->getRoleNames(),
                'created_at'         => $user->created_at->format('d/m/Y à H:i'),
                'email_verified_at'  => $user->email_verified_at?->format('d/m/Y'),
                'profile_photo_url'  => $user->profile_photo_path ? \Storage::url($user->profile_photo_path) : null,
            ],
            'reservations' => $reservations->map(fn($r) => [
                'id'                => $r->id,
                'title'             => $r->title,
                'start_date'        => $r->start_date->format('d/m/Y H:i'),
                'end_date'          => $r->end_date->format('d/m/Y H:i'),
                'status'            => $r->status,
                'status_label'      => $r->status_label,
                'status_color'      => $r->status_color,
                'assets_count'      => $r->assets->count(),
                'is_kit'            => $r->kit_id !== null,
                'kit'               => $r->kit ? ['id' => $r->kit->id, 'name' => $r->kit->name] : null,
                'has_missing_items' => (bool) $r->has_missing_items,
                'assets'            => $r->assets->map(fn($a) => [
                    'id'        => $a->id,
                    'name'      => $a->name,
                    'asset_tag' => $a->asset_tag,
                    'category'  => $a->category?->name,
                ])->values(),
                'created_at'        => $r->created_at->format('d/m/Y'),
            ])->values(),
            'stats'          => $stats,
            'missing_items'  => $missingItems,
            'damage_reports' => $damageReports,
        ]);
    }

    /**
     * Mettre à jour les superviseurs d'un utilisateur (relation many-to-many).
     */
    public function updateUserSupervisors(Request $request, User $user)
    {
        $request->validate([
            'supervisor_ids'   => 'nullable|array',
            'supervisor_ids.*' => 'exists:users,id',
        ]);

        $supervisorIds = $request->input('supervisor_ids', []);

        // Ne pas permettre à un utilisateur d'être son propre superviseur
        $supervisorIds = array_filter($supervisorIds, fn($id) => $id != $user->id);

        // Synchroniser les superviseurs
        $user->supervisors()->sync($supervisorIds);

        return back()->with('success', 'Superviseurs mis à jour avec succès.');
    }
}
