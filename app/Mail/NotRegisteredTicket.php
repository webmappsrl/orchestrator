<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotRegisteredTicket extends Mailable
{
    use Queueable, SerializesModels;

    public $userEmail;
    public $body;
    public string $originalSubject;
    public array $forwardedAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct(string $userEmail, string $originalSubject, string $body, array $forwardedAttachments = [])
    {
        $this->userEmail = $userEmail;
        $this->body = $body;
        $this->originalSubject = $originalSubject;
        $this->forwardedAttachments = $forwardedAttachments;
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
            subject: 'Ticket da utente non registrato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.not-registered-ticket',
            with: [
                'originalBody' => $this->body,
                'userEmail' => $this->userEmail,
                'originalSubject' => $this->originalSubject,
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
        return array_values(array_filter(array_map(function ($a) {
            if (!is_array($a) || !isset($a['name'], $a['content'])) {
                return null;
            }
            $name = (string) $a['name'];
            $content = $a['content'];
            $mime = isset($a['mime']) ? (string) $a['mime'] : null;

            $attachment = Attachment::fromData(fn () => $content, $name);
            if ($mime) {
                $attachment = $attachment->withMime($mime);
            }
            return $attachment;
        }, $this->forwardedAttachments)));
    }
}
