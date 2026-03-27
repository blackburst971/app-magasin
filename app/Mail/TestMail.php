<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $sentAt;

    public function __construct()
    {
        $this->sentAt = now()->format('d/m/Y H:i:s');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Test] Configuration email — App Magasin');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.test');
    }
}
