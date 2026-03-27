<?php

namespace App\Mail;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationAwaitingSupervisor extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public User        $supervisor
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Magasin] Validation requise — ' . $this->reservation->title
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation-awaiting-supervisor');
    }

    public function attachments(): array { return []; }
}
