<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoProject extends Model
{
    protected $fillable = ['name', 'color', 'description', 'created_by', 'is_shared', 'position', 'archived_at'];

    protected $casts = [
        'is_shared'   => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks()
    {
        return $this->hasMany(TodoTask::class, 'project_id')->whereNull('parent_task_id')->orderBy('position');
    }

    public function allTasks()
    {
        return $this->hasMany(TodoTask::class, 'project_id');
    }
}
