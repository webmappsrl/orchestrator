<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'title' => $this->faker->name(),
            'google_drive_url' => $this->faker->url(),
            'customer_id' => Customer::inRandomOrder()->first()->id,

        ];
    }
}
