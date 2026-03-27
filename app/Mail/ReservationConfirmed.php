<?php
namespace App\Mail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public bool $autoCancelEnabled = false,
        public int  $autoCancelHours   = 24,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Magasin] Réservation validée - ' . $this->reservation->title);
    }
    public function content(): Content
    {
        return new Content(view: 'emails.reservation-confirmed');
    }
    public function attachments(): array { return []; }
}
