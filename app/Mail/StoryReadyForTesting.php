<?php

namespace App\Mail;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

class StoryReadyForTesting extends Mailable
{
    use Queueable, SerializesModels;

    public $story;
    public $tester;

    public function __construct(Story $story, $tester)
    {
        $this->story = $story;
        $this->tester = $tester;
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
            subject: 'Il ticket ' . $this->story->id . ' è pronto per essere testato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.story-ready-for-testing',
            with: [
                'story' => $this->story,
                'tester' => $this->tester,
            ],
        );
    }
}
