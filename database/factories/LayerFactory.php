<?php

namespace Database\Factories;

use App\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Layer>
 */
class LayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        if (App::all()->count() == 0) {
            App::factory(10)->create();
        }
        return [
            'name' => $this->faker->name(),
            'title' => [
                'it' => $this->faker->text(20),
                'en' => $this->faker->text(20),
                'de' => $this->faker->text(20),
                'fr' => $this->faker->text(20),
                'es' => $this->faker->text(20),
            ],
            'color' => $this->faker->hexColor(),
            'app_id' => App::inRandomOrder()->first()->id,
            'query_string' => $this->faker->text(10)
        ];
    }
}