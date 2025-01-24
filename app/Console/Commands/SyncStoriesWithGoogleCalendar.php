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

    private $today;
    private $startTime;
    private $currentTimeForDeveloper = [];

    private const DEFAULT_COLOR_ID = '5'; // Yellow
    private const TESTING_COLOR_ID = '6'; // Tangerine
    private const WAITING_COLOR_ID = '8'; // Light Gray
    private const BUG_COLOR_ID = '11'; // Bold Red
    private const HELPDESK_COLOR_ID = '2'; // Green
    private const FEATURE_COLOR_ID = '1'; // Blue
    private const TESTED_COLOR_ID = '3'; // Grape

    public function __construct()
    {
        parent::__construct();
        $this->today = Carbon::today('Europe/Rome');
        $this->startTime = $this->today->setTime(0, 1);
    }

    public function handle()
    {
        $developerId = null;
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
        $todoTickets = $this->getTicketsWithStatus(StoryStatus::Todo->value, $developerId);
        $testingTickets = $this->getTicketsWithStatus(StoryStatus::Test->value, $developerId);
        $testedTickets = $this->getTicketsWithStatus(StoryStatus::Tested->value, $developerId);
        $waitingTickets = $this->getTicketsWithStatus(StoryStatus::Waiting->value, $developerId);

        // Prendi tutti gli ID degli sviluppatori coinvolti
        $developersInvolved = $todoTickets->pluck('user_id')
            ->merge($testingTickets->pluck('user_id'))
            ->merge($testingTickets->pluck('tester_id'))
            ->merge($testedTickets->pluck('user_id'))
            ->merge($waitingTickets->pluck('user_id'))
            ->unique()
            ->toArray();

        // Cancella tutti gli eventi del calendario di ogni sviluppatore coinvolto
        foreach($developersInvolved as $developerId){
            $this->deleteCalendar($developerId);
        }

        // Inizializza il tempo per ogni sviluppatore
        $this->initializeTimeForAllDevelopers($developersInvolved);

        // Crea gli eventi per i ticket di todo
        $todoTickets = $todoTickets->groupBy('user_id');
        $this->createEventsForTickets($todoTickets);
        
        // Pausa di 30 minuti per separare i ticket di testing
        // Aggiorna il tempo per ogni sviluppatore
        $this->add30MinutesToAllDevelopers();

        // Crea gli eventi per i ticket di testing
        $testingTickets = $testingTickets->groupBy('tester_id');
        $this->createEventsForTickets($testingTickets);
        // Crea gli eventi per i ticket di tested
        $testedTickets = $testedTickets->groupBy('user_id');
        $this->createEventsForTickets($testedTickets);

        // Crea gli eventi per i ticket di waiting
        $waitingTickets = $waitingTickets->groupBy('user_id');
        $this->createEventsForTickets($waitingTickets);

        $this->info('All stories have been synced to Google Calendar');
    }

    public function initializeTimeForAllDevelopers($developersInvolved): void{
        foreach($developersInvolved as $developerId){
            $this->currentTimeForDeveloper[$developerId] = $this->startTime->copy();
        }
    }

    public function add30MinutesToAllDevelopers(): void{
        foreach($this->currentTimeForDeveloper as $developerId => $time){
            $this->currentTimeForDeveloper[$developerId] = $time->copy()->addMinutes(30);
        }
    }

    function createEventsForTickets($ticketList): void{
        foreach ($ticketList as $developerId => $tickets) {
            $currentTimeForTicket = $this->currentTimeForDeveloper[$developerId]->copy();
            foreach($tickets as $ticket){
                if($ticket->status == StoryStatus::Test->value && $ticket->tester_id == null){ // Se il ticket non ha un tester, lo assegna allo sviluppatore assegnato
                    $developerId = $ticket->user_id;
                    $currentTimeForTicket = $this->currentTimeForDeveloper[$developerId]->copy();
                }
                $currentTimeForTicket = $this->createEvent($developerId, $ticket, $currentTimeForTicket);
                $this->currentTimeForDeveloper[$developerId] = $currentTimeForTicket;
            }
        }
    }

    private function createEvent($developerId, $ticket, Carbon $startTime){
        $developer = DB::table('users')->where('id', $developerId)->first();

            $endTime = $startTime->copy()->addMinutes(30);

            $colorId = $this->getColorId($ticket);

            // Crea un singolo evento per la storia
            $creator = DB::table('users')->where('id', $ticket->creator_id)->first();
            try {
                Event::create([
                    'name' => "OC: {$ticket->id} [{$creator->name}] - {$ticket->name}", // Nome della storia come titolo dell'evento,
                    'description' => "{$ticket->description}\n\nType: {$ticket->type}, Status: {$ticket->status}\nLink: https://orchestrator.maphub.it/resources/developer-stories/{$ticket->id}",
                    'startDateTime' => $startTime,
                    'endDateTime' => $endTime,
                    'colorId' => $colorId, // Imposta il colore dell'evento
                ], $developer->email);
                $this->info("Event for OC: {$ticket->id} synced to Google Calendar for developer: {$developer->name}");
            } catch (\Exception $e) {
                $this->error("Failed to create event for OC: {$ticket->id}. Error: " . $e->getMessage());
            }

            return $endTime;
    }

    private function deleteCalendar($developerId){
        $developer = DB::table('users')->where('id', $developerId)->first();
        if($developer && $developer->email){
            $calendarId = $developer->email;
            $this->deletePreviousEvents($calendarId);
        }else {
            $this->warn("Developer ID: {$developerId} does not have a valid email.");
        }
    }

    public function getTicketsWithStatus($status, $developerId = null){
        // Query per ottenere i ticket con lo status passato come parametro
        $query = Story::where(function ($query) use ($status) {
            $query->whereIn('status', [$status]);
            if ($status == StoryStatus::Todo->value) {
                $query->orWhereHas('views', function ($query) {
                    $query->whereJsonContains('changes->status', StoryStatus::Todo->value)
                        ->whereDate('viewed_at', $this->today);
                });
            }
        })
            ->whereNotNull('user_id')
            ->whereNotNull('type');
            
        // Se è stato passato un developerId, filtra i ticket in base a quello
        if(isset($developerId)){
            if($status == StoryStatus::Test->value){
                $query = $query->where("tester_id", $developerId);
            }else{
                $query = $query->where("user_id", $developerId);
            }
        }

        return $query->get();
    }

    private function getColorId($story){
        // Imposta il colore dell'evento in base al tipo di storia
        $colorId = self::DEFAULT_COLOR_ID; // Default color (Yellow)
        if($story->status == StoryStatus::Todo->value){
            switch ($story->type) {
                case StoryType::Bug->value:
                    $colorId = self::BUG_COLOR_ID; // Bold Red
                    break;
                case StoryType::Helpdesk->value:
                    $colorId = self::HELPDESK_COLOR_ID; // Green
                    break;
                case StoryType::Feature->value:
                    $colorId = self::FEATURE_COLOR_ID; // Blue
                    break;
            }
        }else{
            switch ($story->status) {
                case StoryStatus::Test->value:
                    $colorId = self::TESTING_COLOR_ID; // Tangerine
                    break;
                case StoryStatus::Waiting->value:
                    $colorId = self::WAITING_COLOR_ID; // Light Gray
                    break;
                case StoryStatus::Tested->value:
                    $colorId = self::TESTED_COLOR_ID; // Grape
                    break;
            }
        }
        return $colorId;
    }
    private function deletePreviousEvents($calendarId)
    {
        try {

            // Ottieni tutti gli eventi nel calendario per oggi
            $events = Event::get(Carbon::today('Europe/Rome'), Carbon::today('Europe/Rome')->endOfDay(), [], $calendarId);
        } catch (\Exception $e) {
            $this->error("Failed to fetch events from Google Calendar. Error: " . $e->getMessage());
            $events = [];
        }

        foreach ($events as $event) {
            // Se il nome dell'evento inizia con "OC: ", cancellalo
            if (strpos($event->name, 'OC:') === 0) {
                try {
                    // Utilizza l'ID dell'evento per cancellarlo
                    $event->delete();
                    $this->info("Deleted event: {$event->name}");
                } catch (\Exception $e) {
                    $this->error("Failed to delete event: {$event->name}. Error: " . $e->getMessage());
                }
            }
        }
    }
}
