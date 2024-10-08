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

        // Create a random number of additional services
        $additionalService = [];
        $numElements = $this->faker->randomNumber(1);
        for ($i = 0; $i < $numElements; $i++) {
            $additionalService = [
                $this->faker->text(10) => $this->faker->randomFloat(2, 0, 100)
            ];
        }


        return [
            'title' => $this->faker->name(),
            'google_drive_url' => $this->faker->url(),
            'customer_id' => Customer::inRandomOrder()->first()->id,
            'discount' => $this->faker->randomFloat(2, 0, 100),
            'additional_services' => $additionalService,
        ];
    }
}
