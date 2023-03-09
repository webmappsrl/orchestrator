<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Product;
use App\Models\Customer;
use App\Models\RecurringProduct;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Quote::factory(100)->create();

        $products = Product::all();
        foreach ($products as $product) {
            $product->quotes()->attach(Quote::inRandomOrder()->first(), ['quantity' => rand(1, 10)]);
        }

        $recurringProducts = RecurringProduct::all();
        foreach ($recurringProducts as $recurringProduct) {
            $recurringProduct->quotes()->attach(Quote::inRandomOrder()->first(), ['quantity' => rand(1, 10)]);
        }
    }
}
