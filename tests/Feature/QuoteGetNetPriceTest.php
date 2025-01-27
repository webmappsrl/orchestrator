<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Quote;
use App\Models\Product;
use App\Models\Customer;
use App\Models\RecurringProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class QuoteGetNetPriceTest extends TestCase
{
    use DatabaseTransactions;
    /**
     * @test
     */
    public function basicTestGetQuoteNetPrice()
    {
        //Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);


        //Create recurring products
        $recurringProducts = RecurringProduct::factory(3)->create([
            'price' => 10,
        ]);


        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 100,
                    'service2' => 50,
                    'service3' => 30,
                ]
            ],
            'discount' => 20
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);
        $quote->recurringProducts()->attach([
            $recurringProducts[0]->id => ['quantity' => 1],
            $recurringProducts[1]->id => ['quantity' => 1],
            $recurringProducts[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
        $this->assertEquals(180, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(220, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoAdditionalServices()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create recurring products
        $recurringProducts = RecurringProduct::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => null,
            'discount' => 20
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);
        $quote->recurringProducts()->attach([
            $recurringProducts[0]->id => ['quantity' => 1],
            $recurringProducts[1]->id => ['quantity' => 1],
            $recurringProducts[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(40, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoDiscount()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create recurring products
        $recurringProducts = RecurringProduct::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 10,
                    'service2' => 20,
                    'service3' => 30,
                ]
            ],
            'discount' => null

        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);
        $quote->recurringProducts()->attach([
            $recurringProducts[0]->id => ['quantity' => 1],
            $recurringProducts[1]->id => ['quantity' => 1],
            $recurringProducts[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(120, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoDiscountAndNoAdditionalServices()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create recurring products
        $recurringProducts = RecurringProduct::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => null,
            'discount' => null
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);
        $quote->recurringProducts()->attach([
            $recurringProducts[0]->id => ['quantity' => 1],
            $recurringProducts[1]->id => ['quantity' => 1],
            $recurringProducts[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(60, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoRecurringProducts()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 10,
                    'service2' => 20,
                    'service3' => 30,
                ]
            ],
            'discount' => 20
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(0, $quote->getTotalRecurringPrice());
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(70, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoRecurringProductsAndNoAdditionalServices()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => null,
            'discount' => 20
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(0, $quote->getTotalRecurringPrice());
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(10, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoRecurringProductsAndNoDiscount()
    {
        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create products
        $products = Product::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 10,
                    'service2' => 20,
                    'service3' => 30,
                ]
            ],
            'discount' => null
        ]);
        $quote->products()->attach([
            $products[0]->id => ['quantity' => 1],
            $products[1]->id => ['quantity' => 1],
            $products[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(30, $quote->getTotalPrice());
        $this->assertEquals(0, $quote->getTotalRecurringPrice());
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(90, $quote->getQuoteNetPrice());
    }


    /**
     * @test
     */

    public function getQuoteNetPriceWithOnlyRecurringProducts()
    {

        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create recurring products
        $recurringProducts = RecurringProduct::factory(3)->create([
            'price' => 10,
        ]);

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 10,
                    'service2' => 20,
                    'service3' => 30,
                ]
            ],
            'discount' => 20
        ]);
        $quote->recurringProducts()->attach([
            $recurringProducts[0]->id => ['quantity' => 1],
            $recurringProducts[1]->id => ['quantity' => 1],
            $recurringProducts[2]->id => ['quantity' => 1],
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(0, $quote->getTotalPrice());
        $this->assertEquals(30, $quote->getTotalRecurringPrice());
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(70, $quote->getQuoteNetPrice());
    }

    /**
     * @test
     */

    public function getQuoteNetPriceWithNoProducts()
    {

        // Create Customer to associate id to the quote
        Customer::factory()->create();

        // Create a quote and associate the products with it
        $quote = Quote::factory()->create([
            'additional_services' => [
                'en' => [
                    'service1' => 10,
                    'service2' => 20,
                    'service3' => 30,
                ]
            ],
            'discount' => 20
        ]);

        // Verify that the net price of the quote is calculated correctly
        $this->assertEquals(0, $quote->getTotalPrice());
        $this->assertEquals(0, $quote->getTotalRecurringPrice());
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
        $this->assertEquals(40, $quote->getQuoteNetPrice());
    }
}
