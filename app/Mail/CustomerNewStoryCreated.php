<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerNewStoryCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $creator;

    /**
     * Create a new message instance.
     */
    public function __construct(Story $story)
    {
        $this->story = $story;
        $this->creator = $story->creator;
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
            subject: $this->creator->name . ' ha creato una nuova storia',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.customer-new-story-created',
            with: [
                'story' => $this->story,
                'creator' => $this->creator,
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
