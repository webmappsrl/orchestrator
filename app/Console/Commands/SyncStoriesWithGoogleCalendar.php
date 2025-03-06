<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use Illuminate\Console\Command;
use App\Models\Story;
use Carbon\Carbon;
use Spatie\GoogleCalendar\Event;
use Illuminate\Support\Facades\DB;
use App\Enums\StoryType;

class SyncStoriesWithGoogleCalendar extends Command
{
    protected $signature = 'sync:stories-calendar {developerEmail?}';
    protected $description = 'Sync assigned stories with Google Calendar';

    private $today;
    private $startTime;
    private $currentTimeForDeveloper = [];

    private const DEFAULT_COLOR_ID = '8'; // Yellow
    private const TESTING_COLOR_ID = '6'; // Tangerine
    private const WAITING_COLOR_ID = '5'; // Light Gray
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
        $developerIds = [];

        // Trova l'ID del developer usando l'email
        if ($developerEmail) {
            $developer = DB::table('users')->where('email', $developerEmail)->first();
            if (!$developer) {
                $this->warn("Developer with email: {$developerEmail} not found.");
                return;
            }
            $developerId = $developer->id;
            $developerIds[] = $developerId;
        } else {
            // Se non è stato passato un email, prendi tutti gli sviluppatori
            $developerIds = DB::table('users')->whereJsonContains('roles', 'developer')->pluck('id')->toArray();
        }
        foreach ($developerIds as $developerId) {
            $this->deleteCalendar($developerId);
            $this->initializeTimeForDeveloper($developerId);
            $todoTickets = $this->getTicketsWithStatus([StoryStatus::Todo->value, StoryStatus::Progress->value], $developerId);
            if (count($todoTickets) > 0) {
                $this->createEventsForTickets($todoTickets, $developerId, StoryStatus::Todo->value);
            }
            $tobeTestedTickets = $this->getTicketsWithStatus([StoryStatus::Test->value], $developerId);
            if (count($tobeTestedTickets) > 0) {
                $this->createEventLabel($developerId, '2BETESTED', $this->currentTimeForDeveloper[$developerId]);
                $this->createEventsForTickets($tobeTestedTickets, $developerId);
            }
            $testedTickets = $this->getTestedTickets($developerId);
            if (count($testedTickets) > 0) {
                $this->createEventLabel($developerId, 'TESTED', $this->currentTimeForDeveloper[$developerId]);
                $this->createEventsForTickets($testedTickets, $developerId);
            }
            $waitingTickets = $this->getTicketsWithStatus([StoryStatus::Waiting->value], $developerId);
            if (count($waitingTickets) > 0) {
                $this->createEventLabel($developerId, 'WAITING', $this->currentTimeForDeveloper[$developerId]);
                $this->createEventsForTickets($waitingTickets, $developerId);
            }
        }

        $this->info('All stories have been synced to Google Calendar');
    }

    public function initializeTimeForDeveloper($developerId): void
    {
        $this->currentTimeForDeveloper[$developerId] = $this->startTime->copy();
    }

    public function add30MinutesToDevelopers($developerId): void
    {
        $this->currentTimeForDeveloper[$developerId] = $time->copy()->addMinutes(30);
    }

    function createEventsForTickets($ticketList, $developerId, $status = null): void
    {
        foreach ($ticketList as  $ticket) {
            $this->createEvent($developerId, $ticket, $status);
        }
    }


    public function getTestedTickets($developerId = null)
    {
        $query = Story::where('status', StoryStatus::Tested->value)
            ->where(function ($query) use ($developerId) {
                $query->where('creator_id', $developerId)
                    ->orWhere(function ($query) use ($developerId) {
                        $query->whereIn('creator_id', function ($subQuery) {
                            $subQuery->select('id')->from('users')->whereJsonContains('roles', 'customer');
                        })->where('user_id', $developerId);
                    });
            });

        return $query->get();
    }

    private function createEvent($developerId, $ticket, $status = null)
    {
        $startTime = $this->currentTimeForDeveloper[$developerId]->copy();

        $developer = DB::table('users')->where('id', $developerId)->first();

        $endTime = $startTime->copy()->addMinutes(30);

        $colorId = $this->getColorId($ticket, $status);

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
            $this->currentTimeForDeveloper[$developerId] = $endTime;
        } catch (\Exception $e) {
            $this->error("Failed to create event for OC: {$ticket->id}. Error: " . $e->getMessage());
        }
    }
    private function createEventLabel($developerId, $label, Carbon $startTime)
    {
        $developer = DB::table('users')->where('id', $developerId)->first();
        $endTime = $startTime->copy()->addMinutes(30);
        try {
            Event::create([
                'name' => "***OC: {$label}***", // Nome della storia come titolo dell'evento,
                'startDateTime' => $startTime,
                'endDateTime' => $endTime,
                'colorId' => self::DEFAULT_COLOR_ID // Imposta il colore dell'evento
            ], $developer->email);
            $this->currentTimeForDeveloper[$developerId] = $endTime;
        } catch (\Exception $e) {
        }
    }

    private function deleteCalendar($developerId)
    {
        $developer = DB::table('users')->where('id', $developerId)->first();
        if ($developer && $developer->email) {
            $calendarId = $developer->email;
            $this->deletePreviousEvents($calendarId);
        } else {
            $this->warn("Developer ID: {$developerId} does not have a valid email.");
        }
    }

    public function getTicketsWithStatus($status = [], $developerId = null)
    {
        // Query per ottenere i ticket con lo status passato come parametro
        $query = Story::where(function ($query) use ($status) {
            $query->whereIn('status', $status);
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
        if (isset($developerId)) {
            if ($status == StoryStatus::Test->value) {
                $query = $query->where("tester_id", $developerId);
            } else {
                $query = $query->where("user_id", $developerId);
            }
        }

        return $query->get();
    }

    private function getColorId($story, $status = null)
    {
        if (is_null($status)) {
            $status = $story->status;
        }
        // Imposta il colore dell'evento in base al tipo di storia
        $colorId = self::DEFAULT_COLOR_ID; // Default color (Yellow)
        if ($status == StoryStatus::Todo->value) {
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
        } else {
            switch ($status) {
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
            if (strpos($event->name, 'OC:') !== false) {
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
