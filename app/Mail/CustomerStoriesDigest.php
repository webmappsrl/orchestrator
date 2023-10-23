<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Story;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class CustomerStoriesDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(protected User $customer)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $mailFromAddress = config('mail.from.address');
        $mailFromName = config('mail.from.name');
        return new Envelope(
            from: new Address($mailFromAddress, $mailFromName),
            subject: 'Orchestrator - Your stories digest',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $doneStories = Story::where('creator_id', $this->customer->id)->where('status', StoryStatus::Done)->get();
        $testStories = Story::where('creator_id', $this->customer->id)->where('status', StoryStatus::Test)->get();
        $progressStories = Story::where('creator_id', $this->customer->id)->where('status', StoryStatus::Progress)->get();
        $newStories = Story::where('creator_id', $this->customer->id)->where('status', StoryStatus::New)->get();
        return new Content(
            view: 'mails.customer-stories-digest',
            with: [
                'doneStories' => $doneStories,
                'testStories' => $testStories,
                'progressStories' => $progressStories,
                'newStories' => $newStories,
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
