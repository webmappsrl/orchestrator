<?php

namespace Database\Seeders;

use App\Models\Deadline;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DeadlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Deadline::factory(10)->create();
    }
}
