<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Epic>
 */
class EpicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(10),
            'user_id' => User::inRandomOrder()->first()->id,
            'milestone_id' => Milestone::inRandomOrder()->first()->id,
        ];
    }
}
