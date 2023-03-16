<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\RecurringProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($rows as $key => $row) {
            if ($key == 0) continue; // skip header row

            $type = $row[0]; // Type is in first column

            if ($type == 'product') {
                $product = new Product([
                    'name' => $row[1], // Name is in second column
                    'description' => $row[2], // Description is in third column
                    'price' => $row[3], // Price is in fourth column
                    'sku' => $row[4], // SKU is in fifth column
                ]);
                $product->save();
            } else if ($type == 'recurring') {
                $recurringProduct = new RecurringProduct([
                    'name' => $row[1],
                    'description' => $row[2],
                    'price' => $row[3],
                    'sku' => $row[4],
                ]);
                $recurringProduct->save();
            }
        }
    }
}