<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RecurringProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class RecurringProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        RecurringProduct::factory(100)->create();
    }
}
