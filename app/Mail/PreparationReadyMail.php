<?php
namespace App\Mail;

use App\Models\Preparation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PreparationReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Preparation $preparation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Magasin] Matériel en cours de préparation — ' . $this->preparation->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.preparation-ready');
    }

    public function attachments(): array { return []; }
}
