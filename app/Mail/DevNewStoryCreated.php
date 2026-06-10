<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DevNewStoryCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $creator;

    public function __construct(Story $story)
    {
        $this->story = $story;
        $this->creator = $story->creator;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: '[new][' . $this->creator->name . ']: ' . $this->story->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.dev-new-story-created',
            with: [
                'story' => $this->story,
                'creator' => $this->creator,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
