<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\RecurringProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToCollection, withHeadingRow
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {


            $type = $row['type']; // Type is in first column

            if ($type == 'product') {
                Product::updateOrCreate([
                    'sku' => $row['sku'],
                ], [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                ]);
            } else if ($type == 'recurring') {
                RecurringProduct::updateOrCreate([
                    'sku' => $row['sku'],
                ], [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'price' => $row['price'],
                ]);
            }
        }
    }
}
