<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Console\Commands\SyncStoriesWithGoogleCalendar;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\Tag;
use App\Nova\AssignedToMeStory;
use App\Nova\CustomerStory;
use App\Nova\DeveloperStory;
use App\Nova\Dashboards\Kanban;
use App\Nova\StoryShowedByCustomer;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Services\Metrics\StoryMetricsCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PendingReleaseStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_release_case_esiste_con_valore_corretto()
    {
        $this->assertSame('pending_release', StoryStatus::PendingRelease->value);
        $this->assertContains('pending_release', StoryStatus::values());
    }

    public function test_pending_release_si_trova_subito_dopo_tested_nellenum()
    {
        $values = StoryStatus::values();

        $this->assertSame(
            array_search('tested', $values, true) + 1,
            array_search('pending_release', $values, true),
            'pending_release deve seguire immediatamente tested: l\'ordine dei cases determina '
            .'l\'ordine delle opzioni in StoryStatusFilter e fieldTrait::getOptions()'
        );
    }

    /**
     * StoryStatus::label() e' un match ESAUSTIVO SENZA ramo default (a differenza di
     * color() e collapse()): un case aggiunto senza la riga corrispondente in label()
     * solleva \UnhandledMatchError, cioe' un 500 sul dashboard Kanban e su ogni index.
     * Questo test protegge anche gli stati futuri, non solo pending_release.
     */
    public function test_ogni_case_dellenum_ha_label_e_colore()
    {
        foreach (StoryStatus::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotSame('', $label, "label() vuota per il case {$case->name}");

            $this->assertMatchesRegularExpression(
                '/^#[0-9A-Fa-f]{6}$/',
                $case->color(),
                "color() non e' un hex valido per il case {$case->name}"
            );
        }
    }

    public function test_pending_release_ha_un_colore_distinto_da_tested_e_released()
    {
        $this->assertSame('#14B8A6', StoryStatus::PendingRelease->color());

        $this->assertNotSame(StoryStatus::Tested->color(), StoryStatus::PendingRelease->color());
        $this->assertNotSame(StoryStatus::Released->color(), StoryStatus::PendingRelease->color());
    }

    /**
     * Doppia chiave obbligatoria: label() cerca "Pending Release", mentre
     * fieldTrait::getOptions() e displayUsing() costruiscono la chiave con
     * __(ucfirst($value)) e cercano quindi "Pending_release".
     * Vedi CLAUDE.md -> ## Convenzioni del codebase.
     */
    public function test_entrambe_le_chiavi_di_traduzione_esistono_in_it_e_en()
    {
        foreach (['it', 'en'] as $locale) {
            $translations = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);

            $this->assertArrayHasKey('Pending Release', $translations, "manca 'Pending Release' in {$locale}.json");
            $this->assertArrayHasKey('Pending_release', $translations, "manca 'Pending_release' in {$locale}.json");

            $this->assertSame(
                $translations['Pending Release'],
                $translations['Pending_release'],
                "le due chiavi devono avere lo stesso valore tradotto in {$locale}.json"
            );
        }
    }

    public function test_ucfirst_del_valore_produce_la_chiave_con_underscore()
    {
        // Documenta il motivo della doppia chiave: ucfirst() non tocca gli underscore.
        $this->assertSame('Pending_release', ucfirst(StoryStatus::PendingRelease->value));
    }

    private function kanbanCard(): \Webmapp\KanbanCard\KanbanCard
    {
        // Kanban::cards() legge auth()->user() per initialFilterValue.
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->actingAs($admin);

        $cards = (new Kanban())->cards();

        return $cards[0];
    }

    public function test_il_kanban_ha_una_colonna_pending_release()
    {
        $values = array_column($this->kanbanCard()->columnsConfig, 'value');

        $this->assertContains('pending_release', $values);
    }

    public function test_la_colonna_pending_release_sta_tra_tested_by_others_e_released()
    {
        $values = array_column($this->kanbanCard()->columnsConfig, 'value');

        $this->assertSame(
            array_search('tested_by_others', $values, true) + 1,
            array_search('pending_release', $values, true)
        );
        $this->assertSame(
            array_search('pending_release', $values, true) + 1,
            array_search('released', $values, true)
        );
    }

    /**
     * statusFilterOverrides definisce DI CHI e' la card in una colonna. Per
     * pending_release il test e' concluso, quindi la card interessa chi ha
     * sviluppato e chi ha aperto il ticket (come released), non chi ha testato
     * (come tested). Senza override esplicito filtrerebbe sul default user_id
     * e le card create da customer non comparirebbero a nessuno.
     */
    public function test_pending_release_filtra_su_user_id_e_creator_id()
    {
        $overrides = $this->kanbanCard()->statusFilterOverrides;

        $this->assertArrayHasKey('pending_release', $overrides);
        $this->assertEqualsCanonicalizing(
            ['user_id', 'creator_id'],
            (array) $overrides['pending_release']
        );
    }

    private function novaRequestFor(User $user): NovaRequest
    {
        return NovaRequest::create('/')->setUserResolver(fn () => $user);
    }

    private function makeStory(array $attributes): Story
    {
        // status va forzato: StoryFactory assegna uno stato casuale fra tutti i cases.
        return Story::factory()->create($attributes);
    }

    public function test_pending_release_escluso_da_customer_stories()
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);

        $pending = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::Tested->value]);

        $ids = CustomerStory::indexQuery($this->novaRequestFor($admin), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids, 'i ticket in tested devono restare visibili');
    }

    public function test_pending_release_escluso_da_developer_stories()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);

        $pending = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = DeveloperStory::indexQuery($this->novaRequestFor($dev), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }

    public function test_pending_release_escluso_da_assigned_to_me()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);
        $this->actingAs($dev); // AssignedToMeStory::indexQuery() usa auth()->user()->id

        $pending = $this->makeStory(['user_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['user_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = AssignedToMeStory::indexQuery($this->novaRequestFor($dev), Story::query())->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }

    /**
     * StoryShowedByCustomer (/resources/story-showed-by-customers, "I miei Ticket")
     * e' la vista DEL CLIENTE, non del team: il cliente deve continuare a vedere i
     * ticket in attesa del suo ok. Test di NON regressione: quel file non va toccato.
     */
    public function test_il_cliente_continua_a_vedere_pending_release_nei_suoi_ticket()
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);

        $pending = $this->makeStory(['creator_id' => $customer->id, 'status' => StoryStatus::PendingRelease->value]);

        $ids = StoryShowedByCustomer::indexQuery($this->novaRequestFor($customer), Story::query())->pluck('id');

        $this->assertContains($pending->id, $ids);
    }

    /**
     * getTestedTickets() filtra con where('status', 'tested') ESATTO: spostando il
     * ticket in pending_release esce automaticamente dal calendario, zero righe di
     * codice. Test di regressione a protezione di quell'assunzione.
     */
    public function test_pending_release_non_finisce_nel_calendario_del_developer()
    {
        $dev = User::factory()->create(['roles' => [UserRole::Developer]]);

        $pending = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['creator_id' => $dev->id, 'status' => StoryStatus::Tested->value]);

        $ids = (new SyncStoriesWithGoogleCalendar())->getTestedTickets($dev->id)->pluck('id');

        $this->assertNotContains($pending->id, $ids);
        $this->assertContains($tested->id, $ids);
    }

    /**
     * Il SAL e' una metrica INTERNA (Tag e TagGroup vivono nella MenuSection('DEV'),
     * visibile solo a Admin/Manager/Developer). Quando il developer dichiara un ticket
     * concluso e in attesa di rilascio, il lavoro sull'RDO e' erogato e il SAL deve
     * rifletterlo. `tested` resta invece FUORI: decisione esplicita, vedi overview.md.
     */
    public function test_pending_release_conta_come_chiuso_nel_sal()
    {
        $this->assertContains('pending_release', Tag::salClosedStoryStatusValues());
        $this->assertNotContains('tested', Tag::salClosedStoryStatusValues());
    }

    public function test_sal_ticket_counts_include_i_pending_release()
    {
        $tag = Tag::create(['name' => 'rdo-test-8426']);

        $pending = $this->makeStory(['status' => StoryStatus::PendingRelease->value]);
        $tested = $this->makeStory(['status' => StoryStatus::Tested->value]);
        $released = $this->makeStory(['status' => StoryStatus::Released->value]);

        $tag->tagged()->attach([$pending->id, $tested->id, $released->id]);

        [$closed, $total] = $tag->salTicketCounts();

        $this->assertSame(3, $total);
        $this->assertSame(2, $closed, 'pending_release e released contano come chiusi, tested no');
    }

    public function test_un_tag_con_soli_pending_release_e_considerato_chiuso()
    {
        $tag = Tag::create(['name' => 'rdo-test-8426-closed']);

        $tag->tagged()->attach($this->makeStory(['status' => StoryStatus::PendingRelease->value])->id);

        $this->assertTrue($tag->isClosed());
    }

    /**
     * getStatusLogs() usa una cache statica self::$logCache[$storyId] senza metodo
     * pubblico di reset: ogni test deve usare una Story NUOVA (id diverso), altrimenti
     * legge i log del test precedente. Non riusare la stessa story fra due asserzioni.
     */
    private function logStatusChange(Story $story, string $status, string $viewedAt): void
    {
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $story->user_id,
            'changes' => ['status' => $status],
            'viewed_at' => $viewedAt,
        ]);
    }

    public function test_pending_release_e_uno_stato_avanzato_per_i_reopen()
    {
        $story = $this->makeStory(['status' => StoryStatus::Todo->value]);

        // pending_release -> todo = il cliente ha bocciato: e' una rilavorazione
        $this->logStatusChange($story, StoryStatus::PendingRelease->value, '2026-08-01 10:00:00');
        $this->logStatusChange($story, StoryStatus::Todo->value, '2026-08-02 10:00:00');

        $this->assertSame(1, (new StoryMetricsCalculator())->reopenCount($story->id));
    }

    public function test_avanzare_da_pending_release_a_released_non_e_un_reopen()
    {
        $story = $this->makeStory(['status' => StoryStatus::Released->value]);

        $this->logStatusChange($story, StoryStatus::PendingRelease->value, '2026-08-01 10:00:00');
        $this->logStatusChange($story, StoryStatus::Released->value, '2026-08-02 10:00:00');

        $this->assertSame(0, (new StoryMetricsCalculator())->reopenCount($story->id));
    }
}
