<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;
use Webmapp\KanbanCard\Http\Controllers\KanbanController;

/**
 * Il Kanban Sales rifiuta il drop in «Chiuso Perso»: una trattativa persa si chiude solo dal form,
 * dove il motivo è obbligatorio (oc:8732). Gli altri Kanban non configurano il blocco.
 */
class KanbanCardDeniedDropTest extends TestCase
{
    use DatabaseTransactions;

    private const MESSAGE = 'To close a quote as lost, open it and write the reason in the edit form.';

    private function makeQuote(): Quote
    {
        return Quote::create([
            'title' => 'Portale Parco XY',
            'customer_id' => Customer::factory()->create()->id,
            'status' => QuoteStatus::Presented->value,
        ]);
    }

    private function config(array $deniedDropStatuses): array
    {
        return [
            'model' => Quote::class,
            'statusField' => 'status',
            'deniedUpdateRoles' => [],
            'allowedUpdateRoles' => [],
            'deniedDropStatuses' => $deniedDropStatuses,
            'priorityField' => null,
            'enableIntraColumnReorder' => false,
        ];
    }

    private function drop(Quote $quote, string $status, array $deniedDropStatuses)
    {
        $request = new Request([
            'status' => $status,
            'config' => $this->config($deniedDropStatuses),
        ]);

        return app(KanbanController::class)->updateStatus($request, $quote->id);
    }

    public function test_drop_into_a_denied_column_is_refused_with_the_translated_message(): void
    {
        app()->setLocale('it');
        $quote = $this->makeQuote();

        $response = $this->drop($quote, QuoteStatus::Closed_Lost->value, [QuoteStatus::Closed_Lost->value => self::MESSAGE]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'Per chiudere una trattativa come persa aprila e scrivi il motivo nel form di modifica.',
            $response->getData(true)['error']
        );
        $this->assertSame(QuoteStatus::Presented->value, $quote->fresh()->status);
    }

    public function test_drop_into_another_column_is_saved(): void
    {
        $quote = $this->makeQuote();

        $response = $this->drop($quote, QuoteStatus::Waiting_For_Order->value, [QuoteStatus::Closed_Lost->value => self::MESSAGE]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(QuoteStatus::Waiting_For_Order->value, $quote->fresh()->status);
    }

    public function test_without_denied_columns_nothing_is_blocked(): void
    {
        $quote = $this->makeQuote();

        $response = $this->drop($quote, QuoteStatus::Closed_Lost->value, []);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(QuoteStatus::Closed_Lost->value, $quote->fresh()->status);
    }

    public function test_sales_dashboard_denies_the_drop_into_closed_lost_and_stories_kanban_does_not(): void
    {
        $sales = collect((new \App\Nova\Dashboards\Sales)->cards())
            ->first(fn ($card) => $card instanceof \Webmapp\KanbanCard\KanbanCard);
        $this->assertSame([QuoteStatus::Closed_Lost->value => self::MESSAGE], $sales->getApiConfig()['deniedDropStatuses']);

        $stories = collect((new \App\Nova\Dashboards\Kanban)->cards())
            ->first(fn ($card) => $card instanceof \Webmapp\KanbanCard\KanbanCard);
        $this->assertSame([], $stories->getApiConfig()['deniedDropStatuses']);
    }
}
