<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TodoTaskReminder extends Model
{
    protected $fillable = [
        'todo_task_id',
        'reminder_at',
        'reminder_channel',
        'reminder_sent_at',
    ];

    protected $casts = [
        'reminder_at'      => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(TodoTask::class, 'todo_task_id');
    }
}
