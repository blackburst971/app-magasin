<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'department',
        'supervisor_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Superviseur direct (champ supervisor_id) */
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /** Utilisateurs dont cet utilisateur est le superviseur direct */
    public function subordinates()
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    /** Superviseurs multiples (relation many-to-many via user_supervisors) */
    public function supervisors()
    {
        return $this->belongsToMany(User::class, 'user_supervisors', 'user_id', 'supervisor_id')
            ->withTimestamps();
    }

    /** Utilisateurs dont cet utilisateur est superviseur (relation many-to-many inverse) */
    public function supervises()
    {
        return $this->belongsToMany(User::class, 'user_supervisors', 'supervisor_id', 'user_id')
            ->withTimestamps();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function approvedReservations()
    {
        return $this->hasMany(Reservation::class, 'approved_by');
    }

    public function groups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'group_id')
            ->withPivot('role');
    }

    public function supervisedGroups()
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_members', 'user_id', 'group_id')
            ->wherePivot('role', 'supervisor');
    }

    /**
     * Retourne le superviseur de cet utilisateur.
     * Priorité :
     * 1. Superviseurs multiples via user_supervisors (retourne collection)
     * 2. supervisor_id direct (ancien système, retourne un seul)
     * 3. Superviseur via les groupes
     */
    public function getSupervisor(): ?User
    {
        // 1. Superviseurs multiples via table pivot
        $multiSupervisors = $this->supervisors;
        if ($multiSupervisors->isNotEmpty()) {
            return $multiSupervisors->first(); // Retourner le premier pour compatibilité
        }

        // 2. Superviseur direct (ancien système)
        if ($this->supervisor_id) {
            return $this->supervisor;
        }

        // 3. Fallback : superviseur via les groupes
        $memberGroupIds = $this->groups()
            ->wherePivot('role', 'member')
            ->pluck('user_groups.id');

        if ($memberGroupIds->isEmpty()) {
            return null;
        }

        return User::whereHas('groups', fn($q) =>
            $q->whereIn('user_groups.id', $memberGroupIds)
              ->wherePivot('role', 'supervisor')
        )->first();
    }

    /**
     * Retourne TOUS les superviseurs de cet utilisateur (collection).
     * Combine : user_supervisors + supervisor_id + groupes
     */
    public function getAllSupervisors()
    {
        $supervisors = collect();

        // 1. Superviseurs via table pivot user_supervisors
        $supervisors = $supervisors->merge($this->supervisors);

        // 2. Superviseur direct via supervisor_id (ancien système)
        if ($this->supervisor_id && $this->supervisor) {
            $supervisors->push($this->supervisor);
        }

        // 3. Superviseurs via les groupes
        $memberGroupIds = $this->groups()
            ->wherePivot('role', 'member')
            ->pluck('user_groups.id');

        if ($memberGroupIds->isNotEmpty()) {
            $groupSupervisors = User::whereHas('groups', fn($q) =>
                $q->whereIn('user_groups.id', $memberGroupIds)
                  ->wherePivot('role', 'supervisor')
            )->get();
            $supervisors = $supervisors->merge($groupSupervisors);
        }

        return $supervisors->unique('id')->values();
    }

    /**
     * Vérifie si cet utilisateur est superviseur :
     * — via un groupe (rôle 'supervisor' dans user_group_members)
     * — OU via le lien direct supervisor_id (au moins un utilisateur a supervisor_id = $this->id)
     */
    public function isSupervisor(): bool
    {
        return $this->supervisedGroups()->exists()
            || $this->subordinates()->exists();
    }

    /**
     * Retourne la query des utilisateurs supervisés par cet utilisateur :
     * — membres de ses groupes (via pivot role=member)
     * — ET/OU subordonné(s) direct(s) via supervisor_id
     */
    public function supervisedUsers()
    {
        // Subordonné(s) via groupes
        $groupIds = $this->supervisedGroups()->pluck('user_groups.id');
        $fromGroupIds = $groupIds->isNotEmpty()
            ? User::whereHas('groups', fn($q) =>
                $q->whereIn('user_groups.id', $groupIds)->wherePivot('role', 'member')
              )->pluck('id')
            : collect();

        // Subordonné(s) direct(s) via supervisor_id
        $directIds = $this->subordinates()->pluck('id');

        $allIds = $fromGroupIds->merge($directIds)->unique()->values();

        return User::whereIn('id', $allIds);
    }
}
