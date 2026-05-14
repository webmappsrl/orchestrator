<?php

namespace Database\Factories;

use App\Models\TagGroup;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagGroupConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tag_group_id' => TagGroup::factory(),
            'tag_id' => Tag::factory(),
            'group_index' => 0,
        ];
    }
}
