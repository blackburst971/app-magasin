<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Diagram extends Model
{
    protected $fillable = ['name', 'description', 'data', 'created_by'];

    protected $casts = [
        'data' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events()
    {
        return $this->belongsToMany(Event::class, 'diagram_event');
    }
}
