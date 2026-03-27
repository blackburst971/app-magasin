<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserGroupController extends Controller
{
    public function index()
    {
        $groups = UserGroup::with(['members'])->withCount('members')->get()->map(fn($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'description' => $g->description,
            'members_count' => $g->members_count,
            'members' => $g->members->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->pivot->role,
            ]),
        ]);

        return Inertia::render('Admin/UserGroups', [
            'groups' => $groups,
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'department']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        UserGroup::create($data);

        return back()->with('success', 'Groupe créé.');
    }

    public function update(Request $request, UserGroup $group)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $group->update($data);

        return back()->with('success', 'Groupe mis à jour.');
    }

    public function destroy(UserGroup $group)
    {
        $group->delete();

        return back()->with('success', 'Groupe supprimé.');
    }

    public function addMember(Request $request, UserGroup $group)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:member,supervisor',
        ]);

        $group->members()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role']]]);

        return back()->with('success', 'Membre ajouté.');
    }

    public function removeMember(UserGroup $group, User $user)
    {
        $group->members()->detach($user->id);

        return back()->with('success', 'Membre retiré.');
    }
}
