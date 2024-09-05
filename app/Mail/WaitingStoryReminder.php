<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class WaitingStoryReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $story;

    /**
     * Create a new message instance.
     */
    public function __construct(Story $story)
    {
        $this->story = $story;
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
            subject: 'Orchestrator Reminder: Il tuo ticket è in attesa di risposta',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mails.waiting-story-reminder',
            with: [
                'story' => $this->story,
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
