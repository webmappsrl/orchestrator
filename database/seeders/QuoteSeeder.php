<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $products = Product::factory(100)->create();
        foreach ($products as $product) {
            $product->quotes()->attach(Quote::factory(1)->create(), ['quantity' => rand(1, 10)]);
        }
    }
}
