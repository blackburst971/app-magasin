<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    protected $fillable = ['name', 'description'];

    public function members()
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'group_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function supervisors()
    {
        return $this->belongsToMany(User::class, 'user_group_members', 'group_id', 'user_id')
            ->wherePivot('role', 'supervisor')
            ->withTimestamps();
    }
}
