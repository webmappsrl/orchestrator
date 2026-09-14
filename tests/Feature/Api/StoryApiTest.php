<?php

namespace Tests\Feature\Api;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create(['roles' => [UserRole::Developer]]);
    }

    /** @test */
    public function get_story_autenticato_restituisce_campi_corretti(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create([
            'name'   => 'Test story',
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Feature->value,
        ]);

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'status', 'type', 'description',
                'customer_request', 'user_id', 'tester_id', 'creator_id',
                'parent_id', 'estimated_hours', 'hours',
                'tags', 'created_at', 'updated_at',
            ])
            ->assertJsonFragment(['name' => 'Test story']);
    }

    /** @test */
    public function get_story_senza_autenticazione_restituisce_401(): void
    {
        $story = Story::factory()->create();

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function get_story_non_esistente_restituisce_404(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->getJson('/api/stories/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function crea_story_con_campi_validi_restituisce_201(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'name'        => 'Nuova feature via API',
            'type'        => StoryType::Feature->value,
            'description' => 'Note tecniche della feature',
            'status'      => StoryStatus::New->value,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nuova feature via API']);

        $this->assertDatabaseHas('stories', ['name' => 'Nuova feature via API']);
    }

    /** @test */
    public function crea_story_senza_name_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'type' => StoryType::Feature->value,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function crea_story_con_status_non_valido_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'name'   => 'Test',
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /** @test */
    public function aggiorna_story_con_campi_validi(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Vecchio nome']);

        $response = $this->patchJson("/api/stories/{$story->id}", [
            'name'        => 'Nuovo nome',
            'description' => 'Note aggiornate',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Nuovo nome']);

        $this->assertDatabaseHas('stories', [
            'id'   => $story->id,
            'name' => 'Nuovo nome',
        ]);
    }

    /** @test */
    public function aggiorna_story_non_tocca_campi_non_passati(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create([
            'name'   => 'Nome originale',
            'status' => StoryStatus::New->value,
        ]);

        $this->patchJson("/api/stories/{$story->id}", [
            'description' => 'Solo descrizione aggiornata',
        ]);

        $this->assertDatabaseHas('stories', [
            'id'   => $story->id,
            'name' => 'Nome originale',
        ]);
    }

    /** @test */
    public function aggiorna_story_con_type_non_valido_restituisce_422(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create();

        $response = $this->patchJson("/api/stories/{$story->id}", [
            'type' => 'InvalidType',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    /** @test */
    public function crea_story_via_api_applica_tag_trimestre(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->postJson('/api/stories', [
            'name' => 'Story con tag trimestre',
            'type' => StoryType::Feature->value,
        ]);

        $response->assertStatus(201);

        $storyId = $response->json('id');
        $story = Story::find($storyId);

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertTrue(
            $story->tags->contains('name', $quarterName),
            "Il tag trimestre '{$quarterName}' non è stato applicato alla story creata via API"
        );
    }

    /** @test */
    public function aggiorna_story_via_api_applica_tag_trimestre(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create(['creator_id' => $this->developer->id]);

        $response = $this->patchJson("/api/stories/{$story->id}", [
            'name' => 'Story aggiornata via API',
        ]);

        $response->assertStatus(200);

        $story->refresh();
        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertTrue(
            $story->tags->contains('name', $quarterName),
            "Il tag trimestre '{$quarterName}' non è stato applicato alla story aggiornata via API"
        );
    }

    /** @test */
    public function crea_story_via_api_non_rimuove_tag_manuali(): void
    {
        Sanctum::actingAs($this->developer);

        $manualTag = Tag::factory()->create(['name' => 'tag-manuale']);

        $response = $this->postJson('/api/stories', [
            'name' => 'Story con tag manuale',
            'type' => StoryType::Feature->value,
            'tags' => [$manualTag->id],
        ]);

        $response->assertStatus(201);

        $storyId = $response->json('id');
        $story = Story::find($storyId);

        $this->assertTrue(
            $story->tags->contains('id', $manualTag->id),
            'Il tag manuale è stato rimosso dopo applicazione auto-tag'
        );

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertTrue(
            $story->tags->contains('name', $quarterName),
            "Il tag trimestre '{$quarterName}' non è presente insieme al tag manuale"
        );
    }

    /** @test */
    public function lista_story_senza_autenticazione_restituisce_401(): void
    {
        $response = $this->getJson('/api/stories');

        $response->assertStatus(401);
    }

    /** @test */
    public function lista_story_senza_filtri_non_esclude_nessuno_stato(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Done->value]);
        Story::factory()->create(['status' => StoryStatus::Rejected->value]);
        Story::factory()->create(['status' => StoryStatus::Released->value]);

        $response = $this->getJson('/api/stories?per_page=50');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status');
        $this->assertTrue($statuses->contains(StoryStatus::Done->value));
        $this->assertTrue($statuses->contains(StoryStatus::Rejected->value));
        $this->assertTrue($statuses->contains(StoryStatus::Released->value));
    }

    /** @test */
    public function lista_story_filtro_status_singolo_valore(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Todo->value]);
        Story::factory()->create(['status' => StoryStatus::Done->value]);

        $response = $this->getJson('/api/stories?status=' . StoryStatus::Todo->value);

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status');
        $this->assertTrue($statuses->every(fn ($s) => $s === StoryStatus::Todo->value));
    }

    /** @test */
    public function lista_story_filtro_status_multiplo(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Todo->value]);
        Story::factory()->create(['status' => StoryStatus::Progress->value]);
        Story::factory()->create(['status' => StoryStatus::Done->value]);

        $response = $this->getJson('/api/stories?' . http_build_query([
            'status' => [StoryStatus::Todo->value, StoryStatus::Progress->value],
        ]));

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status');
        $this->assertTrue($statuses->every(
            fn ($s) => in_array($s, [StoryStatus::Todo->value, StoryStatus::Progress->value], true)
        ));
    }

    /** @test */
    public function lista_story_filtro_tag_id(): void
    {
        Sanctum::actingAs($this->developer);

        $tag = Tag::factory()->create();
        $tagged = Story::factory()->create(['status' => StoryStatus::Todo->value]);
        $tagged->tags()->attach($tag->id);
        Story::factory()->create(['status' => StoryStatus::Todo->value]);

        $response = $this->getJson("/api/stories?tag_id={$tag->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$tagged->id], $ids->all());
    }

    /** @test */
    public function lista_story_filtro_type(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Todo->value, 'type' => StoryType::Feature->value]);
        Story::factory()->create(['status' => StoryStatus::Todo->value, 'type' => StoryType::Bug->value]);

        $response = $this->getJson('/api/stories?type=' . StoryType::Bug->value);

        $response->assertStatus(200);
        $types = collect($response->json('data'))->pluck('type');
        $this->assertTrue($types->every(fn ($t) => $t === StoryType::Bug->value));
    }

    /** @test */
    public function lista_story_filtro_user_id_restituisce_solo_le_story_assegnate(): void
    {
        Sanctum::actingAs($this->developer);

        $assignee = User::factory()->create(['roles' => [UserRole::Developer]]);
        $assigned = Story::factory()->create(['status' => StoryStatus::Todo->value, 'user_id' => $assignee->id]);
        // Explicit, different assignee: StoryFactory otherwise picks a random developer and could
        // coincidentally match $assignee, making this assertion flaky.
        Story::factory()->create(['status' => StoryStatus::Todo->value, 'user_id' => $this->developer->id]);

        $response = $this->getJson("/api/stories?user_id={$assignee->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$assigned->id], $ids->all());
    }

    /** @test */
    public function lista_story_filtro_creator_id_restituisce_solo_le_story_create_dallutente(): void
    {
        Sanctum::actingAs($this->developer);

        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $created = Story::factory()->create(['status' => StoryStatus::Todo->value]);
        // Story::created() auto-assigns creator_id to the currently authenticated user, overriding
        // whatever the factory passed — set the desired value afterward instead.
        $created->forceFill(['creator_id' => $creator->id])->saveQuietly();
        Story::factory()->create(['status' => StoryStatus::Todo->value]);

        $response = $this->getJson("/api/stories?creator_id={$creator->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$created->id], $ids->all());
    }

    /** @test */
    public function lista_story_filtro_created_from_created_to(): void
    {
        Sanctum::actingAs($this->developer);

        $inRange = Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => '2026-01-15']);
        Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => '2026-02-01']);

        $response = $this->getJson('/api/stories?created_from=2026-01-01&created_to=2026-01-31');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$inRange->id], $ids->all());
    }

    /** @test */
    public function lista_story_filtro_updated_from_updated_to(): void
    {
        Sanctum::actingAs($this->developer);

        $inRange = Story::factory()->create(['status' => StoryStatus::Todo->value]);
        $inRange->forceFill(['updated_at' => '2026-01-15'])->saveQuietly();
        $outOfRange = Story::factory()->create(['status' => StoryStatus::Todo->value]);
        $outOfRange->forceFill(['updated_at' => '2026-02-01'])->saveQuietly();

        $response = $this->getJson('/api/stories?updated_from=2026-01-01&updated_to=2026-01-31');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals([$inRange->id], $ids->all());
    }

    /** @test */
    public function lista_story_sort_esplicito_created_at_e_updated_at(): void
    {
        Sanctum::actingAs($this->developer);

        $older = Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        $newer = Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now(), 'updated_at' => now()]);

        $ascCreated = collect($this->getJson('/api/stories?sort=created_at&per_page=50')->json('data'))->pluck('id');
        $this->assertTrue($ascCreated->search($older->id) < $ascCreated->search($newer->id));

        $descCreated = collect($this->getJson('/api/stories?sort=-created_at&per_page=50')->json('data'))->pluck('id');
        $this->assertTrue($descCreated->search($newer->id) < $descCreated->search($older->id));

        $ascUpdated = collect($this->getJson('/api/stories?sort=updated_at&per_page=50')->json('data'))->pluck('id');
        $this->assertTrue($ascUpdated->search($older->id) < $ascUpdated->search($newer->id));

        $descUpdated = collect($this->getJson('/api/stories?sort=-updated_at&per_page=50')->json('data'))->pluck('id');
        $this->assertTrue($descUpdated->search($newer->id) < $descUpdated->search($older->id));
    }

    /** @test */
    public function lista_story_filtro_numerico_non_valido_restituisce_422_non_zero_silenzioso(): void
    {
        Sanctum::actingAs($this->developer);

        foreach (['tag_id', 'user_id', 'creator_id', 'changed_by'] as $param) {
            $this->getJson("/api/stories?{$param}=abc")->assertStatus(422);
        }
    }

    /** @test */
    public function lista_story_with_logs_non_genera_una_query_per_story(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        Sanctum::actingAs($user);

        foreach (range(1, 5) as $i) {
            $story = Story::factory()->create(['status' => StoryStatus::Todo->value]);
            $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Progress->value]);
        }

        // Warm-up call: Spatie permissions issues one `select * from permissions` the first time
        // it's needed per process and caches it — without this, the very first measured call
        // below would carry that one-off cost and make the comparison flaky, unrelated to N+1.
        $this->getJson('/api/stories?per_page=1')->assertStatus(200);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->getJson('/api/stories?with=logs&per_page=2')->assertStatus(200);
        $countWithTwo = count(\Illuminate\Support\Facades\DB::getQueryLog());

        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->getJson('/api/stories?with=logs&per_page=5')->assertStatus(200);
        $countWithFive = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $this->assertEquals(
            $countWithTwo,
            $countWithFive,
            'il numero di query non deve crescere con il numero di story nella pagina (niente N+1 su with=logs)'
        );
    }

    /** @test */
    public function lista_story_paginazione_restituisce_data_e_meta(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->count(3)->create(['status' => StoryStatus::Todo->value]);

        $response = $this->getJson('/api/stories?per_page=2&page=1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.per_page'));
    }

    /** @test */
    public function lista_story_per_page_negativo_viene_clampato_e_non_disabilita_la_paginazione(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->count(3)->create(['status' => StoryStatus::Todo->value]);

        $response = $this->getJson('/api/stories?per_page=-1');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.per_page'));
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function lista_story_per_page_oltre_il_massimo_viene_clampato(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->getJson('/api/stories?per_page=999999');

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('meta.per_page'));
    }

    /** @test */
    public function lista_story_filtro_data_malformato_restituisce_422_non_500(): void
    {
        Sanctum::actingAs($this->developer);

        foreach (['created_from', 'created_to', 'updated_from', 'updated_to', 'changed_from', 'changed_to'] as $param) {
            $this->getJson("/api/stories?{$param}=not-a-date")
                ->assertStatus(422);
        }
    }

    /** @test */
    public function lista_story_filtro_data_valido_continua_a_funzionare(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()]);

        $response = $this->getJson('/api/stories?created_from=' . now()->toDateString());

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data'));
    }

    /** @test */
    public function lista_story_sort_created_at_default_discendente(): void
    {
        Sanctum::actingAs($this->developer);

        $older = Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()->subDay()]);
        $newer = Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()]);

        $response = $this->getJson('/api/stories?per_page=50');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->search($newer->id) < $ids->search($older->id));
    }

    /** @test */
    public function lista_story_sort_status_sconosciuto_o_bloccato_ricade_su_default(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()->subDay()]);
        Story::factory()->create(['status' => StoryStatus::Todo->value, 'created_at' => now()]);

        $withStatusSort = $this->getJson('/api/stories?sort=status&per_page=50')->json('data');
        $withDefaultSort = $this->getJson('/api/stories?per_page=50')->json('data');

        $this->assertEquals(
            collect($withDefaultSort)->pluck('id')->all(),
            collect($withStatusSort)->pluck('id')->all(),
            'sort=status deve ricadere sullo stesso ordine di default finché StoryStatus::sortOrder() non esiste'
        );
    }

    /** @test */
    public function lista_story_with_description_aggiunge_i_campi_solo_se_richiesto(): void
    {
        Sanctum::actingAs($this->developer);

        Story::factory()->create([
            'status' => StoryStatus::Todo->value,
            'description' => 'Nota tecnica riservata',
        ]);

        $default = $this->getJson('/api/stories?per_page=1')->json('data.0');
        $this->assertArrayNotHasKey('description', $default);
        $this->assertArrayNotHasKey('customer_request', $default);

        $withDescription = $this->getJson('/api/stories?per_page=1&with=description')->json('data.0');
        $this->assertArrayHasKey('description', $withDescription);
        $this->assertArrayHasKey('customer_request', $withDescription);
    }

    /** @test */
    public function distingue_assegnatario_user_id_da_autore_modifica_changed_by(): void
    {
        $userA = User::factory()->create(['roles' => [UserRole::Developer]]);
        $userB = User::factory()->create(['roles' => [UserRole::Developer]]);

        $story = Story::factory()->create([
            'status'  => StoryStatus::Todo->value,
            'user_id' => $userA->id,
        ]);

        Sanctum::actingAs($userB);
        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Progress->value])
            ->assertStatus(200);

        $byChangedBy = $this->getJson("/api/stories?changed_by={$userB->id}")->json('data');
        $this->assertTrue(collect($byChangedBy)->pluck('id')->contains($story->id));

        $byUserId = $this->getJson("/api/stories?user_id={$userB->id}")->json('data');
        $this->assertFalse(collect($byUserId)->pluck('id')->contains($story->id));
    }

    /** @test */
    public function log_annidati_rispettano_i_filtri_changed_from_changed_to(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = Story::factory()->create(['status' => StoryStatus::Todo->value]);

        Sanctum::actingAs($user);

        $realNow = now();

        Carbon::setTestNow($realNow->copy()->subDays(2));
        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Progress->value])
            ->assertStatus(200);
        Carbon::setTestNow();

        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Test->value])
            ->assertStatus(200);

        $today = $realNow->toDateString();
        $response = $this->getJson(
            "/api/stories?changed_by={$user->id}&changed_from={$today}&changed_to={$today}&with=logs&per_page=50"
        );

        $item = collect($response->json('data'))->firstWhere('id', $story->id);
        $this->assertNotNull($item, 'La story con una modifica odierna deve comparire nei risultati');
        $this->assertNotEmpty($item['logs']);
        foreach ($item['logs'] as $log) {
            $this->assertStringStartsWith($today, $log['at']);
        }
    }

    /** @test */
    public function log_watch_e_tag_sono_esclusi_da_with_logs_e_dal_filtro_changed(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = Story::factory()->create(['status' => StoryStatus::Todo->value]);

        StoryLog::create([
            'story_id'  => $story->id,
            'user_id'   => $user->id,
            'viewed_at' => now(),
            'changes'   => ['watch' => now()->format('Y-m-d H:i:s')],
        ]);
        StoryLog::create([
            'story_id'  => $story->id,
            'user_id'   => $user->id,
            'viewed_at' => now()->format('Y-m-d H:i'),
            'changes'   => ['tag_attached' => 1],
        ]);

        Sanctum::actingAs($this->developer);

        $byChangedBy = $this->getJson("/api/stories?changed_by={$user->id}")->json('data');
        $this->assertFalse(
            collect($byChangedBy)->pluck('id')->contains($story->id),
            'Una story con solo log "watch"/"tag_attached" non deve comparire come modificata da changed_by'
        );

        $withLogs = $this->getJson("/api/stories?with=logs&per_page=50")->json('data');
        $item = collect($withLogs)->firstWhere('id', $story->id);
        $this->assertEmpty($item['logs'] ?? []);
    }

    /** @test */
    public function get_stories_id_logs_restituisce_storico_paginato_senza_filtri_changed(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = Story::factory()->create(['status' => StoryStatus::Todo->value]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Progress->value])
            ->assertStatus(200);
        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Test->value])
            ->assertStatus(200);

        $response = $this->getJson("/api/stories/{$story->id}/logs");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['at', 'user_id', 'changes']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
    }

    /** @test */
    public function get_stories_id_logs_per_page_negativo_viene_clampato(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        $story = Story::factory()->create(['status' => StoryStatus::Todo->value]);

        Sanctum::actingAs($user);
        $this->patchJson("/api/stories/{$story->id}", ['status' => StoryStatus::Progress->value])
            ->assertStatus(200);

        $response = $this->getJson("/api/stories/{$story->id}/logs?per_page=-1");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.per_page'));
    }

    /** @test */
    public function get_stories_id_logs_senza_autenticazione_restituisce_401(): void
    {
        $story = Story::factory()->create();

        $response = $this->getJson("/api/stories/{$story->id}/logs");

        $response->assertStatus(401);
    }
}
