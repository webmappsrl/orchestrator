<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Nova\Quote as QuoteResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class QuoteNovaResourceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * fields() now nests raw fields inside Tab::group()/Tab::make() (oc:8407),
     * both instances of Laravel\Nova\Fields\FieldMergeValue (their children
     * live in the public $data property). Flatten before searching.
     */
    private function flattenFields(array $fields): array
    {
        $flat = [];

        foreach ($fields as $field) {
            if ($field instanceof \Laravel\Nova\Fields\FieldMergeValue) {
                $flat = array_merge($flat, $this->flattenFields($field->data));
            } else {
                $flat[] = $field;
            }
        }

        return $flat;
    }

    private function fieldPosition(array $fields, callable $matcher): int
    {
        foreach ($fields as $i => $field) {
            if ($matcher($field)) {
                return $i;
            }
        }

        $this->fail('Field not found among fields()');
    }

    public function test_cards_are_both_half_width()
    {
        $request = NovaRequest::create('/');
        $cards = (new QuoteResource(new Quote()))->cards($request);

        $this->assertCount(2, $cards);
        foreach ($cards as $card) {
            $this->assertSame('1/2', $card->width);
        }
    }

    public function test_index_columns_are_in_the_expected_order()
    {
        $request = NovaRequest::create('/');
        $fields = $this->flattenFields((new QuoteResource(new Quote()))->fields($request));

        $customerPos = $this->fieldPosition($fields, fn ($f) => $f instanceof BelongsTo && $f->attribute === 'customer' && $f->showOnIndex === true);
        $titlePos = $this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Title') && $f->showOnIndex === true);
        $statusPos = $this->fieldPosition($fields, fn ($f) => $f instanceof Status && $f->showOnIndex === true);
        $ownerPos = $this->fieldPosition($fields, fn ($f) => $f instanceof BelongsTo && $f->attribute === 'user' && $f->showOnIndex === true);
        $dueDatePos = $this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Due date'));
        $totalPos = $this->fieldPosition($fields, fn ($f) => $f instanceof Currency && $f->attribute === 'total');
        $pdfPos = $this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === 'PDF');

        $this->assertTrue($customerPos < $titlePos);
        $this->assertTrue($titlePos < $statusPos);
        $this->assertTrue($statusPos < $ownerPos);
        $this->assertTrue($ownerPos < $dueDatePos);
        $this->assertTrue($dueDatePos < $totalPos);
        $this->assertTrue($totalPos < $pdfPos);
    }

    public function test_index_title_shows_only_active_locale()
    {
        $quote = $this->makeQuote();
        $quote->setTranslation('title', 'it', 'Titolo italiano');
        $quote->setTranslation('title', 'en', 'English title');
        $quote->save();

        app()->setLocale('en');

        $fields = $this->flattenFields((new QuoteResource($quote))->fields(NovaRequest::create('/')));
        $titleField = $fields[$this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Title') && $f->showOnIndex === true)];
        $titleField->resolveForDisplay($quote);

        $this->assertStringContainsString('English title', $titleField->value);
        $this->assertStringNotContainsString('Titolo italiano', $titleField->value);

        app()->setLocale('it');
    }

    public function test_products_and_recurring_columns_are_hidden_from_index()
    {
        $request = NovaRequest::create('/');
        $fields = $this->flattenFields((new QuoteResource(new Quote()))->fields($request));

        $hiddenLabels = [__('Products'), 'Recurring Products', __('Recurring')];
        $hiddenCount = 0;

        foreach ($fields as $field) {
            if (($field instanceof \Laravel\Nova\Fields\BelongsToMany || $field instanceof Currency)
                && in_array($field->name, $hiddenLabels, true)) {
                $this->assertFalse($field->showOnIndex, "Field {$field->name} should be hidden from index");
                $hiddenCount++;
            }
        }

        $this->assertSame(4, $hiddenCount);
    }

    public function test_due_date_column_shows_nearest_future_todo_task()
    {
        $quote = $this->makeQuote();
        Task::create(['quote_id' => $quote->id, 'title' => 'Futuro lontano', 'due_date' => now()->addDays(10)]);
        $near = Task::create(['quote_id' => $quote->id, 'title' => 'Futuro vicino', 'due_date' => now()->addDay()]);

        $loaded = QuoteResource::indexQuery(NovaRequest::create('/'), Quote::query()->whereKey($quote->id))->first();

        $fields = $this->flattenFields((new QuoteResource($loaded))->fields(NovaRequest::create('/')));
        $dueDateField = $fields[$this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Due date'))];
        $dueDateField->resolveForDisplay($loaded);

        $this->assertSame($near->due_date->format('d/m/Y'), $dueDateField->value);
    }

    public function test_due_date_column_shows_overdue_task_instead_of_dash()
    {
        $quote = $this->makeQuote();
        $overdue = Task::create(['quote_id' => $quote->id, 'title' => 'Scaduto', 'due_date' => now()->subDays(3)]);

        $loaded = QuoteResource::indexQuery(NovaRequest::create('/'), Quote::query()->whereKey($quote->id))->first();

        $fields = $this->flattenFields((new QuoteResource($loaded))->fields(NovaRequest::create('/')));
        $dueDateField = $fields[$this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Due date'))];
        $dueDateField->resolveForDisplay($loaded);

        $this->assertSame($overdue->due_date->format('d/m/Y'), $dueDateField->value);
        $this->assertNotSame('—', $dueDateField->value);
    }

    public function test_due_date_column_shows_dash_when_no_todo_task()
    {
        $quote = $this->makeQuote();
        Task::create(['quote_id' => $quote->id, 'title' => 'Completato', 'due_date' => now()->addDay(), 'status' => Task::STATUS_COMPLETED]);

        $loaded = QuoteResource::indexQuery(NovaRequest::create('/'), Quote::query()->whereKey($quote->id))->first();

        $fields = $this->flattenFields((new QuoteResource($loaded))->fields(NovaRequest::create('/')));
        $dueDateField = $fields[$this->fieldPosition($fields, fn ($f) => $f instanceof Text && $f->name === __('Due date'))];
        $dueDateField->resolveForDisplay($loaded);

        $this->assertSame('—', $dueDateField->value);
    }

    public function test_index_query_eager_loads_todo_tasks_without_n_plus_one()
    {
        $quoteA = $this->makeQuote();
        $quoteB = $this->makeQuote();
        Task::create(['quote_id' => $quoteA->id, 'title' => 'A', 'due_date' => now()->addDay()]);
        Task::create(['quote_id' => $quoteB->id, 'title' => 'B', 'due_date' => now()->addDay()]);

        $quotes = QuoteResource::indexQuery(NovaRequest::create('/'), Quote::query()->whereIn('id', [$quoteA->id, $quoteB->id]))->get();

        DB::enableQueryLog();
        foreach ($quotes as $quote) {
            $quote->tasks->first();
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }
}
