<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Story;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Webklex\IMAP\Facades\Client;
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

        $folder = $client->getFolder('INBOX');

        // Recupera tutte le email non lette (senza il flag "Seen")
        $messages = $folder->query()->unseen()->get();
        $logger->info('Numero di email da elaborare: ' . $messages->count());

        if ($messages->count() == 0) {
            $logger->info('Nessun nuovo messaggio da elaborare');
            return;
        }

        foreach ($messages as $message) {
            $story = $this->createOrchestratorStoryFromMail($message);
            $attachments = $message->hasAttachments() ? $message->getAttachments() : [];

            if ($story) {
                if (count($attachments) > 0) {
                    foreach ($attachments as $attachment) {
                        $this->associateAttachment($attachment, $story);
                    }
                }
                // Segna la email come letta soltanto se viene creata la story
                $message->setFlag('Seen');
            }
        }
        $client->disconnect();
    }

    private function createOrchestratorStoryFromMail($message)
    {
        try {
            $userEmail = $message->getFrom()[0]->mail;
            $subject = $message->getSubject();
            $body = $message->hasTextBody() ? $message->getTextBody() : $message->getBodies();

            $user = User::where('email', $userEmail)->first();

            if ($user) {
                // Creazione della nuova story
                $story = new Story();
                $story->user_id = $user->id;
                $story->name = $subject;
                $story->description = $body;
                $story->status = StoryStatus::New;
                $story->creator_id = $user->id;
                $story->save();
                $logger->info('Story ID ' . $story->id . ' creata.');
                Mail::to($userEmail)->send(new CustomerStoryFromMail($story));
                return $story;
            } else {
                $logger->warning("Nessun utente trovato per l'email: $userEmail");
                //send email to the user email to notify that the user was not registered on orchestrator and that he must contact info@webmapp.it
                Mail::to($userEmail)->send(new OrchestratorUserNotFound($subject));
                return null;
            }
        } catch (\Exception $e) {
            $logger->error('Error creating story: ' . $e->getMessage());
        }
    }

    private function associateAttachment($attachment, $story)
    {
        $attachmentName = $attachment->getName();
        $mimeType = $attachment->getMimeType();


        // Salva l'allegato in una directory temporanea
        try {
            Storage::put('public/' . $attachmentName, $attachment->getContent());

            $temporaryPath = Storage::path('public/' . $attachmentName);
            $logger->info("Percorso temporaneo: " . $temporaryPath);

            // Controlla se il file esiste
            if (Storage::exists('public/' . $attachmentName)) {
                $logger->info("File temporaneo trovato: " . $temporaryPath);
            } else {
                $logger->error("File temporaneo non trovato: " . $temporaryPath);
            }


            // Verifica il MIME type per decidere la collezione corretta
            if (in_array($mimeType, config('services.media-library.allowed_document_formats'))) {
                $logger->info("File documento: " . $temporaryPath . " MIME type: $mimeType");
                // Se è un documento, aggiungilo alla collezione documents
                $story->addMedia($temporaryPath)
                    ->toMediaCollection('documents');
            } elseif (in_array($mimeType, config('services.media-library.allowed_image_formats'))) {
                $this->logger->info("File immagine: " . $temporaryPath . " MIME type: $mimeType, potrebbero esserci problemi nella visualizzazione Nova");
                // Se è un'immagine, aggiungilo alla collezione images
                $story->addMedia($temporaryPath)
                    ->toMediaCollection('images');
            } else {
                // MIME type non supportato, logga un avviso
                $this->logger->warning("Allegato non supportato: $attachmentName con MIME type $mimeType");
            }

            // Rimuovi il file temporaneo
            Storage::delete('public/' . $attachmentName);
        } catch (\Exception $e) {
            $this->logger->error('Error associating attachment: ' . $e->getMessage());
        }
    }
}
