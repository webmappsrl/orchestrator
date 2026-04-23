<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QuoteTotalsAttributesTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_exposes_gross_total_via_total_attribute_and_net_total_via_net_total_attribute()
    {
        Customer::factory()->create();

        $product = Product::factory()->create(['price' => 100]);

        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 50,
                ],
            ],
            'discount' => 20,
        ]);

        $quote->products()->attach([$product->id => ['quantity' => 1]]);

        // Gross total (no discount): 100 + 50 = 150
        $this->assertSame('150,00 €', $quote->total);

        // Net total (discount applied): 150 - 20 = 130
        $this->assertSame('130,00 €', $quote->net_total);
    }
}

