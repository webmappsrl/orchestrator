<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\AppSeeder;
use Illuminate\Database\Seeder;
use Database\Seeders\EpicSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\StorySeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\MilestoneSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UserSeeder::class);
        $this->call(MilestoneSeeder::class);
        $this->call(EpicSeeder::class);
        $this->call(StorySeeder::class);
        $this->call(AppSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(ProjectSeeder::class);
    }
}