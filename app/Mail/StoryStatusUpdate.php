<?php

namespace App\Mail;

use App\Models\Story;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

class StoryStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $recipient;

    public function __construct(Story $story, User $recipient)
    {
        $this->story = $story;
        $this->recipient = $recipient;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address');
        $name = config('mail.from.name');

        return new Envelope(
            from: new Address($from, $name),
            subject: '[' . __($this->story->status) . ']' . '[' . $this->story->creator->name . ']: ' . $this->story->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.story-status-updated',
            with: [
                'story' => $this->story,
                'recipient' => $this->recipient,
            ],
        );
    }
}
