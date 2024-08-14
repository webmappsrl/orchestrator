<?php

namespace App\Jobs;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessInboundEmails implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        // Ottieni l'account IMAP configurato
        $client = Client::account('default');

        // Connetti al server
        $client->connect();

        // Ottieni la cartella INBOX
        $folder = $client->getFolder('INBOX');

        // Recupera tutte le email non lette (senza il flag "Seen")
        $messages = $folder->query()->unseen()->limit(20)->get();

        if ($messages->count() == 0) {
            Log::info('Nessun nuovo messaggio da elaborare');
            return;
        }

        foreach ($messages as $message) {
            try {
                // Estrai le informazioni principali dell'email
                $userEmail = $message->getFrom()[0]->mail;
                $subject = $message->getSubject();
                $body = $message->hasTextBody() ? $message->getTextBody() : $message->getBodies();
                $attachments = $message->hasAttachments() ? $message->getAttachments() : [];

                // Cerca l'utente corrispondente
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
                    Log::info('Story ID ' . $story->id . ' creata.');

                    // Gestione degli allegati
                    if (count($attachments) > 0) {
                        foreach ($attachments as $attachment) {
                            $attachmentName = $attachment->getName();
                            Storage::put('media/Story/' . $story->id . '/' . $attachmentName, $attachment->getContent());
                        }
                    }

                    // Segna l'email come letta
                    $message->setFlag('Seen');
                } else {
                    Log::warning("Nessun utente trovato per l'email: $userEmail");
                }
            } catch (\Exception $e) {
                Log::error("Errore nell'elaborazione dell'email: " . $e->getMessage());
            }
        }

        // Disconnetti dal server IMAP
        $client->disconnect();
    }
}
