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
            $body = $this->cleanEmailBody($message);

            $user = User::where('email', $userEmail)->first();

            if (!$user) {
                $this->handleUnregisteredUser($userEmail, $subject, $body, $logger);
                $message->setFlag('Seen');
                return null;
            }

            return $this->createStory($user, $subject, $body, $logger);
        } catch (\Exception $e) {
            $logger->error('Error creating story: ' . $e->getMessage());
        }
    }

    private function cleanEmailBody($message)
    {
        $body = $message->hasTextBody() ? $message->getTextBody() : $message->getBodies();
        $body = preg_replace('/---.*?---/s', '', $body);
        return trim($body);
    }

    private function decodeSubject($subject)
    {
        $subject = mb_decode_mimeheader($subject);
        return preg_replace('/^Fwd:\s*/', '', $subject);
    }

    private function handleUnregisteredUser($userEmail, $subject, $body, $logger)
    {
        $logger->warning("Nessun utente trovato per l'email: $userEmail");
        Mail::to($userEmail)->send(new OrchestratorUserNotFound($subject));

        $developers = User::whereJsonContains('roles', UserRole::Developer)->get();
        foreach ($developers as $developer) {
            Mail::to($developer->email)->send(new NotRegisteredTicket($userEmail, $subject, $body));
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
