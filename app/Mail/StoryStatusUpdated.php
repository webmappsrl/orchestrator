<?php

namespace App\Mail;

use App\Models\Story;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StoryStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public Story $story;
    public User $user;

    /**
     * Create a new message instance.
     */
    public function __construct(Story $story, User $user)
    {
        $this->story = $story;
        $this->user = $user;
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
            subject: 'Lo stato della storia ' . $this->story->id . ' è stata aggiornata',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $userType = $this->story->tester_id == $this->user->id ? 'tester' : 'developer';
        return new Content(
            markdown: 'mails.story-status-updated',
            with: [
                'story' => $this->story,
                'user' => $this->user,
                'userType' => $userType,
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
