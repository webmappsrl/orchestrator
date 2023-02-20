<?php

namespace Database\Factories;

use App\Models\Epic;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Milestone;
use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        if (User::whereJsonContains('roles', UserRole::Developer)->count() == 0) {
            User::factory(10)->create(['roles' => UserRole::Developer]);
        }

        if (Epic::count() == 0) {
            Epic::factory(10)->create();
        }

        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(10),
            'status' => collect(StoryStatus::cases())->random(),
            'pull_request_link' => $this->faker->url,
            'user_id' => User::whereJsonContains('roles', UserRole::Developer)->get()->random(),
            'epic_id' => Epic::inRandomOrder()->first()->id,
        ];
    }
}
