<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Quote;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuoteGetTotalAdditionalServicesPriceTest extends TestCase
{
    /**
     * @test
     */
    public function basicTestGetTotalAdditionalServicesPrice()
    {
        $quote = Quote::factory()->create([
            'additional_services' => [
                'service1' => 100,
                'service2' => 50,
                'service3' => 30,

            ],
        ]);
        $this->assertEquals(180, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function advancedTestGetTotalAdditionalServicesPrice()
    {

        $quote = Quote::factory()->create([
            'additional_services' => [
                'service1' => 100,
                'service2' => 50,
                'service3' => 30,
                'service4' => 0,
                'service5' => 0,
                'service6' => 20,
                'service7' => 0,
                'service8' => 2,
                'service9' => 0,
                'service10' => 130,
                'service11' => 0,
                'service12' => 13,
            ],
        ]);
        $this->assertEquals(345, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function testGetTotalAdditionalServicesPriceWithNoAdditionalServices()
    {
        $quote = Quote::factory()->create([
            'additional_services' => [],
        ]);
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function testGetTotalAdditionalServicesPriceWithNullAdditionalServices()
    {
        $quote = Quote::factory()->create([
            'additional_services' => null,
        ]);
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function testGetTotalAdditionalServicesPriceWithNullPrice()
    {

        $quote = Quote::factory()->create([
            'additional_services' => [
                'service1' => null,
                'service2' => 50,
                'service3' => 30,
                'service4' => null

            ]
        ]);
        $this->assertEquals(80, $quote->getTotalAdditionalServicesPrice());
    }
}
