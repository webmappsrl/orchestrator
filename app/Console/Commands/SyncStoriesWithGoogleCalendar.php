<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use Illuminate\Console\Command;
use App\Models\Story;
use Carbon\Carbon;
use Spatie\GoogleCalendar\Event;
use Illuminate\Support\Facades\DB;
use App\Enums\StoryType;
use Spatie\GoogleCalendar\GoogleCalendarFactory;

class SyncStoriesWithGoogleCalendar extends Command
{
    protected $signature = 'sync:stories-calendar {developerEmail?}';
    protected $description = 'Sync assigned stories with Google Calendar';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $developerEmail = $this->argument('developerEmail');

        // Trova l'ID del developer usando l'email
        if ($developerEmail) {
            $developer = DB::table('users')->where('email', $developerEmail)->first();
            if (!$developer) {
                $this->warn("Developer with email: {$developerEmail} not found.");
                return;
            }
            $developerId = $developer->id;
        }
        $today = Carbon::today('Europe/Rome');
        // Ottieni tutte le storie assegnate che non sono chiuse (stato diverso da 'Done', 'Released', 'Rejected' e 'Waiting')
        $query = Story::where(function ($query) use ($today) {
            $query->whereIn('status', [StoryStatus::Todo->value])
                ->orWhereHas('views', function ($query) use ($today) {
                    $query->whereJsonContains('changes->status', StoryStatus::Todo->value)
                        ->whereDate('viewed_at', $today);
                });
        })
            ->whereNotNull('user_id')
            ->whereNotNull('type');

        if (isset($developerId)) {
            $query->where('user_id', $developerId);
        }

        $stories = $query->get();



        // Raggruppa le storie per developer
        $storiesByDeveloper = $stories->groupBy('user_id');

        foreach ($storiesByDeveloper as $developerId => $stories) {
            // Ottieni il developer
            $developer = DB::table('users')->where('id', $developerId)->first();

            if ($developer && $developer->email) {
                // Usa l'email del developer come calendar ID
                $calendarId = $developer->email;

                // Cancella i precedenti eventi creati con questo script
                $this->deletePreviousEvents($calendarId);
                // Inizializza l'orario di inizio per il primo evento
                $startTime = Carbon::today('Europe/Rome')->setTime(0, 1);
                foreach ($stories as $story) {
                    // Definisci l'orario di fine per l'evento
                    $endTime = $startTime->copy()->addMinutes(30);

                    // Imposta il colore dell'evento in base al tipo di storia
                    $colorId = '5'; // Default color (Yellow)
                    switch ($story->type) {
                        case StoryType::Bug->value:
                            $colorId = '11'; // Bold Red
                            break;
                        case StoryType::Helpdesk->value:
                            $colorId = '2'; // Green
                            break;
                        case StoryType::Feature->value:
                            $colorId = '1'; // Blue
                            break;
                    }

                    // Crea un singolo evento per la storia
                    $event = new Event;
                    $creator = DB::table('users')->where('id', $story->creator_id)->first();
                    $event->name = "OC: {$story->id} [{$creator->name}] - {$story->name}"; // Nome della storia come titolo dell'evento
                    $event->description = "{$story->description}\n\nType: {$story->type}, Status: {$story->status}\nLink: https://orchestrator.maphub.it/resources/developer-stories/{$story->id}";
                    $event->startDateTime = $startTime;
                    $event->endDateTime = $endTime;
                    $event->colorId = $colorId; // Imposta il colore dell'evento

                    // Salva l'evento nel calendario specifico del developer
                    try {
                        $event->save(null, ['calendarId' => $calendarId]);
                        $this->info("Event for OC: {$story->id} synced to Google Calendar for developer: {$developer->name}");
                    } catch (\Exception $e) {
                        $this->error("Failed to create event for OC: {$story->id}. Error: " . $e->getMessage());
                    }

                    // Aggiorna l'orario di inizio per il prossimo evento
                    $startTime = $endTime;
                }
            } else {
                $this->warn("Developer ID: {$developerId} does not have a valid email.");
            }
        }

        $this->info('All stories have been synced to Google Calendar');
    }

    private function deletePreviousEvents($calendarId)
    {
        try {

            // Ottieni tutti gli eventi nel calendario per oggi
            $events = Event::get(Carbon::today('Europe/Rome'), Carbon::today('Europe/Rome')->endOfDay(), ['calendarId' => $calendarId]);
        } catch (\Exception $e) {
            $this->error("Failed to fetch events from Google Calendar. Error: " . $e->getMessage());
            $events = [];
        }

        foreach ($events as $event) {
            // Se il nome dell'evento inizia con "OC: ", cancellalo
            if (strpos($event->name, 'OC:') === 0) {
                try {
                    // Utilizza l'ID dell'evento per cancellarlo
                    $calendar = GoogleCalendarFactory::createForCalendarId($calendarId);
                    $calendar->deleteEvent($event->id);
                    $this->info("Deleted event: {$event->name}");
                } catch (\Exception $e) {
                    $this->error("Failed to delete event: {$event->name}. Error: " . $e->getMessage());
                }
            }
        }
    }
}
