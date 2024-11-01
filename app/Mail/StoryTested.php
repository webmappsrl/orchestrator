<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

class StoryTested extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $developer;

    public function __construct(Story $story, $developer)
    {
        $this->story = $story;
        $this->developer = $developer;
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
            subject: 'Il ticket ' . $this->story->id . ' è stato testato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.story-tested',
            with: [
                'story' => $this->story,
                'developer' => $this->developer,
            ],
        );
    }
}
