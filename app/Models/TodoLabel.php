<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoLabel extends Model
{
    protected $fillable = ['name', 'color', 'created_by'];

    public function tasks()
    {
        return $this->belongsToMany(TodoTask::class, 'todo_task_labels', 'label_id', 'task_id');
    }
}
