<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'color',
        'start_date',
        'end_date',
        'responsible_user_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function diagrams()
    {
        return $this->belongsToMany(Diagram::class, 'diagram_event');
    }
}
