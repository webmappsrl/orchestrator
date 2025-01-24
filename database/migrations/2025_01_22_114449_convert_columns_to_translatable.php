<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\RecurringProduct;
use App\Models\Quote;

return new class extends Migration
{
    public function up()
    {
        // Convert Products
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            if (!is_null($product->name)) {
                $model = Product::find($product->id);
                $model->setTranslation('name', 'it', $product->name);
                $model->setTranslation('description', 'it', $product->description);
                $model->save();
            }
        }

        // Convert RecurringProducts
        $recurringProducts = DB::table('recurring_products')->get();
        foreach ($recurringProducts as $recurringProduct) {
            if (!is_null($recurringProduct->name)) {
                $model = RecurringProduct::find($recurringProduct->id);
                $model->setTranslation('name', 'it', $recurringProduct->name);
                $model->setTranslation('description', 'it', $recurringProduct->description);
                $model->save();
            }
        }

        // Convert Quotes
        $quotes = DB::table('quotes')->get();
        foreach ($quotes as $quote) {
            foreach (['title', 'notes', 'additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $field) {
                if (!is_null($quote->$field)) {
                    $model = Quote::find($quote->id);
                    $model->setTranslation($field, 'it', $quote->$field);
                    $model->save();
                }
            }
        }
    }

    public function down()
    {
        // Revert Products
        $products = DB::table('products')->get();
        foreach ($products as $product) {
            $model = Product::find($product->id);
            if ($model->getTranslation('name', 'it', false)) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['name' => $model->getTranslation('name', 'it')]);
            }
        }

        // Revert RecurringProducts
        $recurringProducts = DB::table('recurring_products')->get();
        foreach ($recurringProducts as $recurringProduct) {
            $model = RecurringProduct::find($recurringProduct->id);
            if ($model->getTranslation('name', 'it', false)) {
                DB::table('recurring_products')
                    ->where('id', $recurringProduct->id)
                    ->update(['name' => $model->getTranslation('name', 'it')]);
            }
        }

        // Revert Quotes
        $quotes = DB::table('quotes')->get();
        foreach ($quotes as $quote) {
            $model = Quote::find($quote->id);
            foreach (['title', 'notes', 'additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $field) {
                if ($model->getTranslation($field, 'it', false)) {
                    DB::table('quotes')
                        ->where('id', $quote->id)
                        ->update([$field => $model->getTranslation($field, 'it')]);
                }
            }
        }
    }
};
