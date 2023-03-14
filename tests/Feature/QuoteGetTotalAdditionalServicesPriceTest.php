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
                ['description' => 'Service A', 'price' => 10],
                ['description' => 'Service B', 'price' => 20],
                ['description' => 'Service C', 'price' => 30],
            ],
        ]);
        $this->assertEquals(60, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */
    public function testGetTotalAdditionalServicesPriceWithEmptyAdditionalServices()
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

    public function testGetTotalAdditionalServicesPriceWithAdditionalServicesWithNoPrice()
    {
        $quote = Quote::factory()->create([
            'additional_services' => [
                ['description' => 'Service A'],
                ['description' => 'Service B'],
                ['description' => 'Service C'],
            ],
        ]);
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function testGetTotalAdditionalServicesPriceWithAdditionalServicesWithNoDescription()
    {
        $quote = Quote::factory()->create([
            'additional_services' => [
                ['price' => 100],
                ['price' => 50],
                ['price' => 30],
            ],
        ]);
        $this->assertEquals(180, $quote->getTotalAdditionalServicesPrice());
    }

    /**
     * @test
     */

    public function testGetTotalAdditionalServicesPriceWithAdditionalServicesWithNoDescriptionAndNoPrice()
    {
        $quote = Quote::factory()->create([
            'additional_services' => [
                [],
                [],
                [],
            ],
        ]);
        $this->assertEquals(0, $quote->getTotalAdditionalServicesPrice());
    }
}