<?php
namespace App\Policies;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || $user->id === $reservation->user_id;
    }
    public function update(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || ($user->id === $reservation->user_id && $reservation->status === 'pending');
    }
    public function delete(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('admin') || ($user->id === $reservation->user_id && in_array($reservation->status, ['pending', 'approved']));
    }
}
