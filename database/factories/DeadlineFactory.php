<?php

namespace Database\Factories;

use App\Enums\DeadlineStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deadline>
 */
class DeadlineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'due_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'status' => collect(DeadlineStatus::cases())->random(),
        ];
    }
}
