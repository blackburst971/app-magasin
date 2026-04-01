<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoTask extends Model
{
    protected $fillable = [
        'project_id', 'parent_task_id', 'title', 'description',
        'due_date', 'priority', 'status', 'position',
        'assigned_to', 'created_by',
        'reminder_at', 'reminder_channel', 'reminder_sent_at',
    ];

    protected $casts = [
        'due_date'          => 'date',
        'reminder_at'       => 'datetime',
        'reminder_sent_at'  => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(TodoProject::class, 'project_id');
    }

    public function parent()
    {
        return $this->belongsTo(TodoTask::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(TodoTask::class, 'parent_task_id')->orderBy('position');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function labels()
    {
        return $this->belongsToMany(TodoLabel::class, 'todo_task_labels', 'task_id', 'label_id');
    }

    public function reservations()
    {
        return $this->belongsToMany(\App\Models\Reservation::class, 'todo_task_reservations', 'task_id', 'reservation_id');
    }
}
