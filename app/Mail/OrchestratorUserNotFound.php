<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrchestratorUserNotFound extends Mailable
{
    use Queueable, SerializesModels;

    public $sub;

    /**
     * Create a new message instance.
     */
    public function __construct($sub)
    {
        $this->sub = $sub;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromName = config('mail.from.name');
        $fromAddress = config('mail.from.address');
        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Orchestrator: utente non trovato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.user-not-found',
            with: [
                'sub' => $this->sub
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
