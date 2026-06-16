<?php

namespace App\Mail;

use App\Enums\UserRole;
use App\Models\Story;
use Illuminate\Bus\Queueable;
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
    public string $novaUrl;

    public function __construct(Story $story)
    {
        $this->story = $story;
        $this->creator = $story->creator;
        $this->novaUrl = $story->creator->hasRole(UserRole::Customer)
            ? '/resources/customer-stories/' . $story->id
            : '/resources/stories/' . $story->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: '[new][' . $this->story->creator->name . ']: ' . $this->story->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.customer-new-story-created',
            with: [
                'story' => $this->story,
                'creator' => $this->creator,
                'novaUrl' => $this->novaUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
