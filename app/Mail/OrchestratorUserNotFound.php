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

class OrchestratorUserNotFound extends Mailable
{
    use Queueable, SerializesModels;

    public $sub;
    public array $forwardedAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct($sub, array $forwardedAttachments = [])
    {
        $this->sub = $sub;
        $this->forwardedAttachments = $forwardedAttachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromName = config('mail.from.name');
        $fromAddress = config('mail.from.address');
        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: 'Orchestrator: utente non trovato',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mails.user-not-found',
            with: [
                'sub' => $this->sub
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
