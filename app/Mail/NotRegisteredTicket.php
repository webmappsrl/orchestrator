<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotRegisteredTicket extends Mailable
{
    use Queueable, SerializesModels;

    public $userEmail;
    public $subject;
    public $body;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userEmail, string $subject, string $body)
    {
        $this->userEmail = $userEmail;
        $this->subject = $subject;
        $this->body = $body;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $from = config('mail.from.address');
        $name = config('mail.from.name');
        return new Envelope(
            from: new Address($from, $name),
            subject: 'Ticket da utente non registrato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.not-registered-ticket',
            with: [
                'originalBody' => $this->body,
                'userEmail' => $this->userEmail,
                'subject' => $this->subject,
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
