<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

use function GuzzleHttp\json_encode;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        if (Customer::count() == 0) {
            Customer::factory(10)->create();
        }
        $additionalService = json_encode([
            'description' => $this->faker->text(10),
            'price' => $this->faker->randomFloat(2, 0, 100)
        ]);
        return [
            'title' => $this->faker->name(),
            'google_drive_url' => $this->faker->url(),
            'customer_id' => Customer::inRandomOrder()->first()->id,
            'discount' => $this->faker->randomFloat(2, 0, 100),
            'additional_services' => $additionalService,


        ];
    }
}
