<?php

namespace App\Mail;

use App\Models\Story;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
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
    public $context;

    public function __construct(Story $story, User $recipient, array $context = [])
    {
        $this->story = $story;
        $this->recipient = $recipient;
        $this->context = $context;
    }

    public function envelope(): Envelope
    {
        $from = config('mail.from.address');
        $name = config('mail.from.name');
        $isCustomer = $this->recipient->hasRole(UserRole::Customer);

        $statusValue = (string) $this->story->status;
        $statusEnum = StoryStatus::tryFrom($statusValue);
        $statusLabel = $statusEnum?->label() ?? $statusValue;
        $statusLabelUpper = mb_strtoupper($statusLabel, 'UTF-8');

        $subject = $isCustomer
            ? 'Ticket ' . $this->story->name . ' ' . $statusLabelUpper
            : '[' . __($this->story->status) . ']' . '[' . $this->story->creator->name . ']: ' . $this->story->name;

        return new Envelope(
            from: new Address($from, $name),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $isCustomer = $this->recipient->hasRole(UserRole::Customer);
        $view = $isCustomer ? 'mails.story-status-updated-customer' : 'mails.story-status-updated-developer';

        return new Content(
            view: $view,
            with: [
                'story' => $this->story,
                'recipient' => $this->recipient,
                'highlightLatestResponse' => (bool) ($this->context['highlight_latest_response'] ?? false),
            ],
        );
    }
}
