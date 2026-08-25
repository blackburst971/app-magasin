<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'kit_id',
        'title',
        'start_date',
        'end_date',
        'status',
        'notes',
        'admin_notes',
        'approved_by',
        'approved_at',
        'checkout_at',
        'return_at',
        'returned_by_user_id',
        'has_missing_items',
        'supervisor_signature',
        'supervisor_approved_at',
        'supervisor_approved_by',
        'return_reminder_sent_at',
        'google_event_id',
    ];

    protected $casts = [
        'start_date'              => 'datetime',
        'end_date'                => 'datetime',
        'approved_at'             => 'datetime',
        'checkout_at'             => 'datetime',
        'return_at'               => 'datetime',
        'supervisor_approved_at'  => 'datetime',
        'return_reminder_sent_at' => 'datetime',
        'has_missing_items'       => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function kit()
    {
        return $this->belongsTo(Kit::class);
    }

    /** Kits réservés via le panier unifié (pivot reservation_kits) */
    public function kits()
    {
        return $this->belongsToMany(Kit::class, 'reservation_kits')->withTimestamps();
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supervisorApprovedBy()
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'reservation_assets')
            ->withPivot('returned_at', 'checkout_at', 'good_condition', 'return_notes', 'returned_by_user_id', 'is_missing', 'pending_verification', 'verification_note')
            ->withTimestamps();
    }

    public function formResponse()
    {
        return $this->hasOne(CheckoutFormResponse::class);
    }

    public function formResponses()
    {
        return $this->hasMany(CheckoutFormResponse::class);
    }

    public function movements()
    {
        return $this->hasMany(AssetMovement::class);
    }

    public function messages()
    {
        return $this->hasMany(ReservationMessage::class)->orderBy('created_at');
    }

    /** Participants supplémentaires (hors demandeur principal). */
    public function additionalUsers()
    {
        return $this->belongsToMany(User::class, 'reservation_users')->withTimestamps();
    }

    /** Tous les utilisateurs concernés : demandeur + participants. */
    public function allParticipants()
    {
        $extras = $this->additionalUsers->pluck('id')->toArray();
        return User::whereIn('id', array_merge([$this->user_id], $extras))->get();
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'awaiting_supervisor' => 'En attente du responsable',
            'pending'             => 'En attente',
            'approved'            => 'Validée',
            'en_preparation'      => 'En préparation',
            'active'              => 'En cours',
            'returned'            => 'Retourné',
            'cancelled'           => 'Annulée',
            'rejected'            => 'Refusée',
            default               => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'awaiting_supervisor' => '#8b5cf6',
            'pending'             => '#f59e0b',
            'approved'            => '#3b82f6',
            'en_preparation'      => '#0ea5e9',
            'active'              => '#10b981',
            'returned'            => '#6b7280',
            'cancelled'           => '#ef4444',
            'rejected'            => '#ef4444',
            default               => '#6b7280',
        };
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeAwaitingSupervisor($query)
    {
        return $query->where('status', 'awaiting_supervisor');
    }
}
