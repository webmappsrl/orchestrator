<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use App\Nova\ArchivedQuotes;
use App\Nova\Lenses\QuotesWithoutActiveTasks;
use App\Nova\Quote as QuoteResource;
use App\Nova\QuoteNoFilter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * Lens Nova «Trattative senza task attivi» (oc:8729): stessa definizione di
 * GET /api/quotes?without_active_tasks=1, solo sulla risorsa Quote.
 */
class QuotesWithoutActiveTasksLensTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Nova::resourcesIn(app_path('Nova'));
    }

    private function makeQuote(QuoteStatus $status): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
            'status' => $status->value,
        ]);
    }

    /** @test */
    public function la_lens_e_registrata_solo_sulla_risorsa_quote(): void
    {
        $request = NovaRequest::create('/nova-api/quotes', 'GET');

        $lenses = (new QuoteResource(new Quote))->lenses($request);
        $this->assertCount(1, $lenses);
        $this->assertInstanceOf(QuotesWithoutActiveTasks::class, $lenses[0]);

        $this->assertSame([], (new ArchivedQuotes(new Quote))->lenses($request));
        $this->assertSame([], (new QuoteNoFilter(new Quote))->lenses($request));
    }

    /** @test */
    public function la_lens_restituisce_le_trattative_dello_scope(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->actingAs($admin);

        $uncovered = $this->makeQuote(QuoteStatus::New);
        $followed = $this->makeQuote(QuoteStatus::Presented);
        Task::create([
            'quote_id' => $followed->id,
            'title' => 'Richiamare il cliente',
            'due_date' => now()->addDay(),
        ]);
        $closed = $this->makeQuote(QuoteStatus::Closed_Lost);

        // Ordinamento per id decrescente: le trattative appena create stanno nella prima pagina,
        // qualunque cosa contenga il DB di test. Il confronto è limitato a quelle del test.
        $created = [$uncovered->id, $followed->id, $closed->id];
        $ids = collect($this->getJson('/nova-api/quotes/lens/quotes-without-active-tasks')
            ->assertStatus(200)
            ->json('resources'))->pluck('id.value');

        $this->assertSame(
            Quote::query()->openWithoutActiveTasks()->whereKey($created)->pluck('id')->all(),
            $ids->intersect($created)->values()->all(),
        );
        $this->assertSame([$uncovered->id], $ids->intersect($created)->values()->all());
    }

    /** @test */
    public function senza_ordinamento_scelto_la_lens_ordina_per_id_decrescente(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $this->actingAs($admin);

        $older = $this->makeQuote(QuoteStatus::New);
        $newer = $this->makeQuote(QuoteStatus::Presented);

        $ids = collect($this->getJson('/nova-api/quotes/lens/quotes-without-active-tasks')
            ->assertStatus(200)
            ->json('resources'))->pluck('id.value')->all();

        $this->assertSame($ids, collect($ids)->sortDesc()->values()->all());
        $this->assertSame([$newer->id, $older->id], array_slice($ids, 0, 2));
    }
}
