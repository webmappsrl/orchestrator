<?php

namespace App\Mail;

use App\Models\Story;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StoryResponse extends Mailable
{
    use Queueable, SerializesModels;

    public Story $story;
    public User $recipient;
    public User $sender;
    public string $response;

    /**
     * Create a new message instance.
     */
    public function __construct(Story $story, User $recipient, User $sender,  string $response)
    {
        $this->story = $story;
        $this->recipient = $recipient;
        $this->sender = $sender;
        $this->response = $response;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->sender->name . '] responded to story: ' . $this->story->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mails.customer-story-answer',
            with: [
                'story' => $this->story,
                'recipient' => $this->recipient,
                'sender' => $this->sender,
                'response' => $this->response,
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
