<?php

namespace Database\Factories;

use App\Enums\UserRole;
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

        if (User::whereJsonContains('roles', UserRole::Developer)->count() == 0) {
            User::factory(10)->create(['roles' => UserRole::Developer]);
        }

        if(Milestone::count() == 0) {
            Milestone::factory(10)->create();
        }

        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(10),
            'user_id' => User::whereJsonContains('roles', UserRole::Developer)->get()->random(),
            'milestone_id' => Milestone::inRandomOrder()->first()->id,
            'pull_request_link' => $this->faker->url,
        ];
    }
}
