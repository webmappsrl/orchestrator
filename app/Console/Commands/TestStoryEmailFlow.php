<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Mail\CustomerNewStoryCreated;
use App\Models\Story;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class TestStoryEmailFlow extends Command
{
    protected $signature = 'story:test-email-flow
        {--creator-id=134 : ID utente creator/customer}
        {--assignee-id=154 : ID utente assegnatario}
        {--tester-id=104 : ID utente tester}
        {--wait-seconds=2 : Attesa in secondi tra gli step}
        {--response=ticket risolto : Testo risposta finale}';

    protected $description = 'Crea e aggiorna un ticket step-by-step per testare i trigger email.';

    public function handle(): int
    {
        $creatorId = (int) $this->option('creator-id');
        $assigneeId = (int) $this->option('assignee-id');
        $testerId = (int) $this->option('tester-id');
        $waitSeconds = max(0, (int) $this->option('wait-seconds'));
        $finalResponse = (string) $this->option('response');

        $creator = User::find($creatorId);
        $assignee = User::find($assigneeId);
        $tester = User::find($testerId);

        if (! $creator || ! $assignee || ! $tester) {
            $this->error('Utente creator/assignee/tester non trovato. Verifica gli ID passati.');

            return self::FAILURE;
        }

        // Simula utente customer che apre ticket
        Auth::login($creator);

        $title = 'Test '.now()->format('Y-m-d H:i:s');
        $initialCustomerRequest = '<p>Anche a fine registrazione, oltre a durante la registrazione, il dislivello è errato vedi foto, dovrebbe essere circa 350m</p><p></p><img src="https://orchestrator.maphub.it/storage/app dislivello a fine registrazione.jpg" alt="" title="" tt-mode="file" tt-link-url="" tt-link-target="" tt-link-mode="url" class=""><p></p>';

        $story = new Story();
        $story->name = $title;
        $story->creator_id = $creator->id;
        $story->customer_request = $initialCustomerRequest;
        $story->type = StoryType::Helpdesk->value;
        $story->status = StoryStatus::New->value;
        $story->save();

        $this->info("Step 1 - Ticket creato: #{$story->id}");

        $this->info('Step 1b - Creazione: la mail "new story" parte via hook model');
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // CASE NEGATIVO 1 (opzionale): cambia user_id mentre status non è todo
        // In base alle regole, non dovrebbe partire la mail "assigned" corretta.
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->user_id = $assignee->id;
        $story->save();
        $this->info("Case neg 1 - user_id impostato mentre status era New (ticket #{$story->id})");
        $this->pauseBetweenSteps($waitSeconds);

        $story->refresh();
        $story->user_id = null;
        $story->save();
        $this->info("Case neg 1 - reset user_id=null");
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // PRE-STEP: porta lo stato a todo (senza cambiare user_id)
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->status = StoryStatus::Todo->value;
        $story->save();
        $this->info("Pre-step - Stato impostato a todo");
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // 2) assegna user_id e NON cambiare status nello stesso save
        // Serve per far scattare la mail "user_id" nel caso consentito.
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->user_id = $assignee->id;
        $story->save();
        $this->info("Step 2 - Assegnato a user_id={$assignee->id}, stato=todo");
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // CASE NEGATIVO 2 (opzionale): cambia tester_id mentre status != testing
        // Non dovrebbe partire la mail tester.
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->tester_id = $tester->id;
        $story->save();
        $this->info("Case neg 2 - tester_id impostato mentre status era todo (ticket #{$story->id})");
        $this->pauseBetweenSteps($waitSeconds);

        $story->refresh();
        $story->tester_id = null;
        $story->save();
        $this->info("Case neg 2 - reset tester_id=null");
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // PRE-STEP: porta lo stato a testing (senza cambiare tester_id)
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->status = StoryStatus::Test->value;
        $story->save();
        $this->info("Pre-step - Stato impostato a testing");
        $this->pauseBetweenSteps($waitSeconds);

        // ---------------------------------------------------------------------
        // 3) assegna tester_id mantenendo status=testing nello stesso save
        // ---------------------------------------------------------------------
        $story->refresh();
        $story->tester_id = $tester->id;
        $story->save();
        $this->info("Step 3 - Assegnato tester_id={$tester->id}, stato=testing");
        $this->pauseBetweenSteps($waitSeconds);

        // 4) stato tested (il cambio deve essere fatto dal tester)
        Auth::login($tester);
        $story->refresh();
        $story->status = StoryStatus::Tested->value;
        $story->save();
        $this->info('Step 4 - Stato=tested');
        $this->pauseBetweenSteps($waitSeconds);

        // 5) stato released + response "ticket risolto"
        $story->refresh();
        $story->addResponse($finalResponse, false);
        $story->status = StoryStatus::Released->value;
        $story->save();
        $this->info('Step 5 - Stato=released + response aggiunta');
        $this->pauseBetweenSteps($waitSeconds);

        // 6) il customer risponde su ticket released e torna in todo
        Auth::login($creator);
        $story->refresh();
        $story->addResponse('Ho ancora dei problemi', false);
        $story->status = StoryStatus::Todo->value;
        $story->save();
        $this->info('Step 6 - Customer response + Stato=todo');
        $this->pauseBetweenSteps($waitSeconds);

        // 7) l'assegnatario risponde e rimette in released
        Auth::login($assignee);
        $story->refresh();
        $story->addResponse('Ora è tutto corretto', false);
        $story->status = StoryStatus::Released->value;
        $story->save();
        $this->info('Step 7 - Assignee response + Stato=released');

        Auth::logout();

        $this->newLine();
        $this->info("Completato. Ticket test: #{$story->id}");
        $this->line('Nota: le email partono in coda; verifica worker/Horizon attivo.');

        return self::SUCCESS;
    }

    private function sendCustomerNewStoryEmailToDevelopers(Story $story): void
    {
        $developers = User::whereJsonContains('roles', UserRole::Developer)->get();

        foreach ($developers as $developer) {
            Mail::to($developer->email)->send(new CustomerNewStoryCreated($story));
        }
    }

    private function pauseBetweenSteps(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $this->line("Attesa {$seconds}s...");
        sleep($seconds);
    }
}
