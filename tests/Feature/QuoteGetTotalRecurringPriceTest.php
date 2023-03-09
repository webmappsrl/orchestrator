<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Quote;
use App\Models\Customer;
use App\Models\RecurringProduct;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuoteGetTotalRecurringPriceTest extends TestCase
{
    /**
     * 
     *@test
     */
    public function basic_test_get_total_recurring_price()
    {
        // Crea products
        $recurringProduct1 = RecurringProduct::factory()->create([
            'price' => 10,
        ]);
        $recurringProduct2 = RecurringProduct::factory()->create([
            'price' => 10,
        ]);
        $recurringProduct3 = RecurringProduct::factory()->create([
            'price' => 10,
        ]);

        //Crea Customer per associare id alla quote
        Customer::factory()->create();

        // Crea una quote ed associa i prodotti ad essa
        $quote = Quote::factory()->create();
        $quote->recurringProducts()->attach([
            $recurringProduct1->id => ['quantity' => 1],
            $recurringProduct2->id => ['quantity' => 1],
            $recurringProduct3->id => ['quantity' => 1],
        ]);

        // Verifica che il prezzo totale dei prodotti sia calcolato correttamente
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
    }

    /**
     * 
     *@test
     */
    public function advanced_test_get_total_recurring_price()
    {

        $recurringProduct1 = RecurringProduct::factory()->create([
            'price' => 0.5,
        ]);
        $recurringProduct2 = RecurringProduct::factory()->create([
            'price' => 10.00,
        ]);
        $recurringProduct3 = RecurringProduct::factory()->create([
            'price' => 0,
        ]);
        $recurringProduct4 = RecurringProduct::factory()->create([
            'price' => 999.99
        ]);
        $recurringProduct5 = RecurringProduct::factory()->create([
            'price' => 1234.56
        ]);


        Customer::factory()->create();


        $quote = Quote::factory()->create();
        $quote->recurringProducts()->attach([
            $recurringProduct1->id => ['quantity' => 100],
            $recurringProduct2->id => ['quantity' => 25],
            $recurringProduct3->id => ['quantity' => 9999],
            $recurringProduct4->id => ['quantity' => 10],
            $recurringProduct5->id => ['quantity' => 20],
        ]);


        $this->assertEquals(34991.1, $quote->getTotalRecurringPrice());
    }

    /**
     * 
     *@test
     */

    public function when_zero_recurring_products_then_getTotalRecurringPrice_returns_zero()
    {

        $recurringProduct1 = RecurringProduct::factory()->create([
            'price' => 0.5,
        ]);
        $recurringProduct2 = RecurringProduct::factory()->create([
            'price' => 10.00,
        ]);
        $recurringProduct3 = RecurringProduct::factory()->create([
            'price' => 0,
        ]);

        Customer::factory()->create();

        $quote = Quote::factory()->create();

        $quote->recurringProducts()->attach([
            $recurringProduct1->id => ['quantity' => 0],
            $recurringProduct2->id => ['quantity' => 0],
            $recurringProduct3->id => ['quantity' => 0],
        ]);

        $this->assertEquals(0, $quote->getTotalRecurringPrice());
    }

    /**
     * 
     *@test
     */

    public function when_all_recurring_products_price_is_zero_then_getTotalRecurringPrice_returns_zero()
    {
        $recurringProduct1 = RecurringProduct::factory()->create([
            'price' => 0,
        ]);
        $recurringProduct2 = RecurringProduct::factory()->create([
            'price' => 0,
        ]);
        $recurringProduct3 = RecurringProduct::factory()->create([
            'price' => 0,
        ]);

        Customer::factory()->create();

        $quote = Quote::factory()->create();

        $quote->recurringProducts()->attach([
            $recurringProduct1->id => ['quantity' => 145435],
            $recurringProduct2->id => ['quantity' => 1234],
            $recurringProduct3->id => ['quantity' => 154534],
        ]);

        $this->assertEquals(0, $quote->getTotalRecurringPrice());
    }
}
