<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Story;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Illuminate\Bus\Queueable;
use Webklex\IMAP\Facades\Client;
use App\Mail\NotRegisteredTicket;
use App\Mail\CustomerStoryFromMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrchestratorUserNotFound;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessInboundEmails implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $logger = Log::channel('process_inbound_emails');
        $client = Client::account('default');
        $client->connect();

        $messages = $this->getUnseenMessages($client);
        $logger->info('Numero di email da elaborare: ' . $messages->count());

        if ($messages->isEmpty()) {
            $logger->info('Nessun nuovo messaggio da elaborare');
            return;
        }

        foreach ($messages as $message) {
            $this->processMessage($message, $logger);
        }

        $client->disconnect();
    }

    private function getUnseenMessages($client)
    {
        $folder = $client->getFolder('INBOX');
        return $folder->query()->unseen()->get();
    }

    private function processMessage($message, $logger)
    {
        $story = $this->createOrchestratorStoryFromMail($message, $logger);
        $attachments = $message->hasAttachments() ? $message->getAttachments() : [];

        if ($story) {
            $this->handleAttachments($attachments, $story, $logger);
            $message->setFlag('Seen');
        }
    }

    private function handleAttachments($attachments, $story, $logger)
    {
        foreach ($attachments as $attachment) {
            $this->associateAttachment($attachment, $story, $logger);
        }
    }

    private function createOrchestratorStoryFromMail($message, $logger)
    {
        try {
            $userEmail = $message->getFrom()[0]->mail;
            $subject = $this->decodeSubject($message->getSubject());
            $forwardableAttachments = $this->getForwardableAttachments($message);
            $body = $this->getEmailBodyAsHtml($message, $forwardableAttachments);

            $user = User::where('email', $userEmail)->first();

            if (!$user) {
                $this->handleUnregisteredUser($userEmail, $subject, $body, $forwardableAttachments, $logger);
                $message->setFlag('Seen');
                return null;
            }

            return $this->createStory($user, $subject, $body, $logger);
        } catch (\Exception $e) {
            $logger->error('Error creating story: ' . $e->getMessage());
        }
    }

    /**
     * Return the email body as HTML, preserving formatting when possible.
     * Falls back to plain text converted to HTML.
     */
    private function getEmailBodyAsHtml($message, array $forwardableAttachments = []): string
    {
        if (method_exists($message, 'hasHTMLBody') && $message->hasHTMLBody()) {
            $html = (string) $message->getHTMLBody();
            $html = trim($html);
            if ($html !== '') {
                return $this->embedInlineCidImagesAsDataUris($html, $forwardableAttachments);
            }
        }

        $text = '';
        if ($message->hasTextBody()) {
            $text = (string) $message->getTextBody();
        } else {
            $bodies = $message->getBodies();
            $text = is_array($bodies) ? implode("\n", $bodies) : (string) $bodies;
        }

        // Basic cleanup for common forwarded separators in plain text.
        $text = preg_replace('/---.*?---/s', '', $text);
        $text = trim($text);

        return nl2br(e($text));
    }

    private function decodeSubject($subject)
    {
        $subject = mb_decode_mimeheader($subject);
        return preg_replace('/^Fwd:\s*/', '', $subject);
    }

    /**
     * In the "unregistered sender" scenario we don't create any ticket, but we still
     * notify the sender and the developers. We also forward attachments (if any).
     */
    private function handleUnregisteredUser(string $userEmail, string $subject, string $body, array $attachments, $logger): void
    {
        $logger->warning("Nessun utente trovato per l'email: $userEmail");
        Mail::to($userEmail)->send(new OrchestratorUserNotFound($subject, $attachments));

        $developers = User::whereJsonContains('roles', UserRole::Developer)->get();
        foreach ($developers as $developer) {
            Mail::to($developer->email)->send(new NotRegisteredTicket($userEmail, $subject, $body, $attachments));
        }
    }

    private function createStory($user, $subject, $body, $logger)
    {
        $story = new Story();
        $story->name = $subject;
        $story->customer_request = $body;
        $story->type = StoryType::Helpdesk;
        $story->status = StoryStatus::New;
        $story->creator_id = $user->id;
        $story->save();
        $logger->info('Story ID ' . $story->id . ' creata.');
        Mail::to($user->email)->send(new CustomerStoryFromMail($story));
        return $story;
    }

    /**
     * Extract attachments into a serializable array for forwarding via Laravel Mail.
     * Each item: ['name' => string, 'mime' => string|null, 'content' => string]
     */
    private function getForwardableAttachments($message): array
    {
        if (!$message->hasAttachments()) {
            return [];
        }

        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            try {
                $content = $attachment->getContent();
                if ($content === null || $content === '') {
                    continue;
                }
                $attachments[] = [
                    'id' => method_exists($attachment, 'getId') ? (string) $attachment->getId() : null,
                    'name' => (string) $attachment->getName(),
                    'mime' => method_exists($attachment, 'getMimeType') ? (string) $attachment->getMimeType() : null,
                    'disposition' => method_exists($attachment, 'getDisposition') ? (string) $attachment->getDisposition() : null,
                    'content' => $content,
                ];
            } catch (\Exception $e) {
                // best-effort: skip attachment if we can't read it
            }
        }

        return $attachments;
    }

    /**
     * Many email clients embed inline images via `cid:` URLs. When we forward the email,
     * those images would appear broken unless we inline them. For local debugging (Mailpit),
     * converting `cid:` references into `data:` URIs is the most robust approach.
     */
    private function embedInlineCidImagesAsDataUris(string $html, array $attachments): string
    {
        if ($html === '' || stripos($html, 'cid:') === false || empty($attachments)) {
            return $html;
        }

        $byCandidate = [];
        $imageAttachments = [];
        foreach ($attachments as $a) {
            $id = isset($a['id']) ? trim((string) $a['id']) : '';
            // Only inline images
            $mime = isset($a['mime']) ? (string) $a['mime'] : '';
            if ($mime === '' || stripos($mime, 'image/') !== 0) {
                continue;
            }

            $imageAttachments[] = $a;

            $candidates = [];
            if ($id !== '') {
                $candidates[] = $id;
                $candidates[] = trim($id, "<> \t\r\n");
                if (str_contains($id, '@')) {
                    $candidates[] = explode('@', trim($id, "<> \t\r\n"))[0];
                }
            }
            $name = isset($a['name']) ? trim((string) $a['name']) : '';
            if ($name !== '') {
                $candidates[] = $name;
            }
            foreach ($candidates as $c) {
                $c = trim((string) $c);
                if ($c === '') {
                    continue;
                }
                $byCandidate[$c] = $a;
            }
        }

        if (empty($byCandidate) && empty($imageAttachments)) {
            return $html;
        }

        return preg_replace_callback(
            '/\bsrc\s*=\s*(["\'])\s*cid:([^"\'>\s]+)\s*\1/i',
            function ($m) use ($byCandidate, $imageAttachments) {
                $quote = $m[1];
                $cid = trim((string) $m[2]);
                $cid = trim($cid, "<> \t\r\n");

                $a = $byCandidate[$cid] ?? null;

                if (!$a) {
                    // Try fuzzy match (cid may be a token of the real Content-ID)
                    foreach ($byCandidate as $k => $candidateAttachment) {
                        $kNorm = trim((string) $k);
                        if ($kNorm !== '' && (str_contains($kNorm, $cid) || str_contains($cid, $kNorm))) {
                            $a = $candidateAttachment;
                            break;
                        }
                    }
                }

                // If still not found and there's exactly one inline image attachment, embed it as best effort
                if (!$a && count($imageAttachments) === 1) {
                    $a = $imageAttachments[0];
                }

                if (!$a) {
                    return $m[0];
                }

                $mime = (string) ($a['mime'] ?? 'application/octet-stream');
                $content = $a['content'] ?? '';
                if ($content === '') {
                    return $m[0];
                }

                $dataUri = 'data:' . $mime . ';base64,' . base64_encode($content);
                return 'src=' . $quote . $dataUri . $quote;
            },
            $html
        ) ?? $html;
    }

    private function associateAttachment($attachment, $story, $logger)
    {
        $attachmentName = $attachment->getName();
        $mimeType = $attachment->getMimeType();

        try {
            Storage::put('public/' . $attachmentName, $attachment->getContent());
            $temporaryPath = Storage::path('public/' . $attachmentName);
            $logger->info("Percorso temporaneo: " . $temporaryPath);

            if (Storage::exists('public/' . $attachmentName)) {
                $logger->info("File temporaneo trovato: " . $temporaryPath);
                $this->addMediaToStory($mimeType, $temporaryPath, $story, $logger);
            } else {
                $logger->error("File temporaneo non trovato: " . $temporaryPath);
            }

            Storage::delete('public/' . $attachmentName);
        } catch (\Exception $e) {
            $logger->error('Error associating attachment: ' . $e->getMessage());
        }
    }

    private function addMediaToStory($mimeType, $temporaryPath, $story, $logger)
    {
        if (
            in_array($mimeType, config('services.media-library.allowed_document_formats')) ||
            in_array($mimeType, config('services.media-library.allowed_image_formats'))
        ) {
            $logger->info("File: " . $temporaryPath . " MIME type: $mimeType");
            $story->addMedia($temporaryPath)->toMediaCollection('documents');
        } else {
            $logger->warning("Allegato non supportato con MIME type $mimeType");
        }
    }
}
