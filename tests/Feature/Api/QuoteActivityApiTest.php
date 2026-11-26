<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteActivity;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/quote-activities (oc:8724): parametri from/days, filtro delle
 * trattative chiuse prima del periodo, forma della risposta.
 */
class QuoteActivityApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['roles' => [UserRole::Admin], 'name' => 'Alessio Test']);
        Sanctum::actingAs($this->user);
        QuoteActivity::query()->delete();
    }

    /** Esegue $callback con l'orologio fermo all'ora italiana indicata. */
    private function at(string $datetime, callable $callback): mixed
    {
        $this->travelTo(CarbonImmutable::parse($datetime, 'Europe/Rome'));
        $result = $callback();
        $this->travelBack();

        return $result;
    }

    private function makeQuote(string $datetime, array $overrides = []): Quote
    {
        return $this->at($datetime, fn () => Quote::create(array_merge([
            'title' => 'Preventivo Comune di X',
            'customer_id' => Customer::factory()->create(['name' => 'Comune di X'])->id,
            'status' => QuoteStatus::New->value,
        ], $overrides)));
    }

    private function quoteIds($response): array
    {
        return collect($response->json('data'))->pluck('quote.id')->all();
    }

    /** @test */
    public function without_parameters_returns_today(): void
    {
        $yesterday = $this->makeQuote('2026-11-10 10:00');
        $today = $this->makeQuote('2026-11-11 09:00');
        $this->travelTo(CarbonImmutable::parse('2026-11-11 15:00', 'Europe/Rome'));

        $response = $this->getJson('/api/quote-activities')->assertOk();

        $response->assertJsonPath('from', '2026-11-11')->assertJsonPath('days', 1);
        $this->assertSame([$today->id], $this->quoteIds($response));
        $this->assertNotContains($yesterday->id, $this->quoteIds($response));
    }

    /** @test */
    public function from_returns_only_that_day_and_days_extends_the_range(): void
    {
        $day10 = $this->makeQuote('2026-11-10 10:00');
        $day11 = $this->makeQuote('2026-11-11 10:00');
        $day12 = $this->makeQuote('2026-11-12 10:00');

        $one = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();
        $this->assertSame([$day10->id], $this->quoteIds($one));

        $three = $this->getJson('/api/quote-activities?from=2026-11-10&days=2')->assertOk();
        $this->assertEqualsCanonicalizing([$day10->id, $day11->id], $this->quoteIds($three));
        $this->assertNotContains($day12->id, $this->quoteIds($three));
    }

    /** @test */
    public function invalid_parameters_return_422(): void
    {
        $this->getJson('/api/quote-activities?from=10-11-2026')->assertStatus(422);
        $this->getJson('/api/quote-activities?days=0')->assertStatus(422);
        $this->getJson('/api/quote-activities?days=abc')->assertStatus(422);
    }

    /** @test */
    public function days_follow_italian_midnight(): void
    {
        $late = $this->makeQuote('2026-11-10 23:59');
        $early = $this->makeQuote('2026-11-11 00:01');

        $response = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();

        $this->assertSame([$late->id], $this->quoteIds($response));
        $this->assertNotContains($early->id, $this->quoteIds($response));
    }

    /** @test */
    public function quote_closed_before_the_period_is_excluded_closed_during_is_included(): void
    {
        $closedBefore = $this->makeQuote('2026-11-01 10:00');
        $this->at('2026-11-05 10:00', fn () => $closedBefore->update(['status' => QuoteStatus::Closed_Won->value]));
        $this->at('2026-11-10 10:00', fn () => Task::create(['quote_id' => $closedBefore->id, 'title' => 'Fattura', 'due_date' => now()]));

        $closedDuring = $this->makeQuote('2026-11-01 10:00');
        $this->at('2026-11-10 11:00', fn () => $closedDuring->update(['status' => QuoteStatus::Closed_Lost->value, 'lost_reason' => 'Prezzo']));

        $response = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();

        $this->assertSame([$closedDuring->id], $this->quoteIds($response));
    }

    /** @test */
    public function quote_closed_without_status_change_in_the_diary_counts_as_closed_before(): void
    {
        $quote = $this->makeQuote('2026-11-01 10:00', ['status' => QuoteStatus::Closed_Won->value]);
        $this->at('2026-11-10 10:00', fn () => $quote->update(['notes' => 'Archiviata']));

        $response = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();

        $this->assertSame([], $this->quoteIds($response));
    }

    /** @test */
    public function response_is_grouped_by_quote_and_self_describing(): void
    {
        $quote = $this->makeQuote('2026-11-10 09:00');
        $task = $this->at('2026-11-10 10:14', fn () => Task::create(['quote_id' => $quote->id, 'title' => 'Richiamare il cliente', 'due_date' => now()]));
        $this->at('2026-11-10 11:30', fn () => $quote->update(['status' => QuoteStatus::Presented->value]));

        $response = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quote.id', $quote->id)
            ->assertJsonPath('data.0.quote.title', 'Preventivo Comune di X')
            ->assertJsonPath('data.0.quote.status', 'presented')
            ->assertJsonPath('data.0.quote.customer.name', 'Comune di X')
            ->assertJsonPath('data.0.quote.deleted', false)
            ->assertJsonPath('data.0.activities.0.event', QuoteActivity::EVENT_QUOTE_CREATED)
            ->assertJsonPath('data.0.activities.1.event', QuoteActivity::EVENT_TASK_CREATED)
            ->assertJsonPath('data.0.activities.1.at', '2026-11-10T10:14:00+01:00')
            ->assertJsonPath('data.0.activities.1.user.name', 'Alessio Test')
            ->assertJsonPath('data.0.activities.1.task.id', $task->id)
            ->assertJsonPath('data.0.activities.1.task.title', 'Richiamare il cliente')
            ->assertJsonPath('data.0.activities.2.event', QuoteActivity::EVENT_QUOTE_UPDATED)
            ->assertJsonPath('data.0.activities.2.changes.status', ['old' => 'new', 'new' => 'presented']);
    }

    /** @test */
    public function deleted_quote_appears_with_copied_title(): void
    {
        $quote = $this->makeQuote('2026-11-10 09:00');
        $this->at('2026-11-10 12:00', fn () => $quote->delete());

        $response = $this->getJson('/api/quote-activities?from=2026-11-10')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.quote.id', null)
            ->assertJsonPath('data.0.quote.title', 'Preventivo Comune di X')
            ->assertJsonPath('data.0.quote.deleted', true)
            ->assertJsonCount(2, 'data.0.activities');
    }

    /** @test */
    public function activity_without_user_is_attributed_to_system(): void
    {
        $this->app['auth']->forgetGuards();
        $quote = $this->makeQuote('2026-11-10 09:00');
        Sanctum::actingAs($this->user);

        $this->getJson('/api/quote-activities?from=2026-11-10')
            ->assertOk()
            ->assertJsonPath('data.0.quote.id', $quote->id)
            ->assertJsonPath('data.0.activities.0.user', ['id' => null, 'name' => 'Sistema']);
    }

    /** @test */
    public function unauthenticated_gets_401_and_customer_gets_403(): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/quote-activities')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create(['roles' => [UserRole::Customer]]));
        $this->getJson('/api/quote-activities')->assertStatus(403);
    }
}
