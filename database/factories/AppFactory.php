<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\App>
 */
class AppFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => [
                'it' => $this->faker->name(),
                'en' => $this->faker->name(),
                'de' => $this->faker->name(),
                'fr' => $this->faker->name(),
                'es' => $this->faker->name(),
            ],
            'description' => [
                'it' => $this->faker->text(),
                'en' => $this->faker->text(),
                'de' => $this->faker->text(),
                'fr' => $this->faker->text(),
                'es' => $this->faker->text(),
            ],
        ];
    }
}
