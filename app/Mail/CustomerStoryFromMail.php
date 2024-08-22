<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerStoryFromMail extends Mailable
{
    use Queueable, SerializesModels;
    public $story;

    /**
     * Create a new message instance.
     */
    public function __construct($story)
    {
        $this->story = $story;
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
            subject: 'La tua segnalazione è stata registrata',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.customer-story-from-mail',
            with: ['story' => $this->story],
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
