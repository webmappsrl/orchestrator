<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Nova\Kanban\SalesQuoteColumnAggregator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SalesQuoteColumnAggregatorTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_sums_net_quote_prices_and_counts_all_items()
    {
        Customer::factory()->create();

        $product = Product::factory()->create(['price' => 100]);

        $quoteA = Quote::factory()->create([
            'additional_services' => null,
            'discount' => 10,
        ]);
        $quoteA->products()->attach([$product->id => ['quantity' => 1]]);
        $this->assertSame(90.0, $quoteA->getQuoteNetPrice());

        $quoteB = Quote::factory()->create([
            'additional_services' => [
                'en' => ['s1' => 50],
            ],
            'discount' => 0,
        ]);
        $quoteB->products()->attach([$product->id => ['quantity' => 2]]);
        $this->assertSame(250.0, $quoteB->getQuoteNetPrice());

        $items = new Collection([$quoteA, $quoteB, 'not-a-quote']);

        $result = (new SalesQuoteColumnAggregator())->aggregate(
            $items,
            Request::create('/'),
            'any',
            []
        );

        // Count is the number of items in the column, regardless of type.
        $this->assertSame(3, $result['count']);

        // Sum uses only Quote items and uses net price (discount applied).
        $this->assertSame(340.0, $result['sum']);
    }
}
