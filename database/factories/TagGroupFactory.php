<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TagGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
        ];
    }
}
