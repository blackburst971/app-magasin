<?php
namespace App\Mail;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CheckoutSummary extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public Reservation $reservation) {}
    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Magasin] Récapitulatif de retrait - ' . $this->reservation->title);
    }
    public function content(): Content
    {
        return new Content(view: 'emails.checkout-summary');
    }
    public function attachments(): array { return []; }
}
