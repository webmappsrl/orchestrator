<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Product;
use App\Models\RecurringProduct;

class ProductPdfController extends Controller
{
    public function download(Request $request)
    {
        // Recupera gli ID dei prodotti dal parametro della query
        $productIds = $request->query('products', []);
        $recurringProductIds = $request->query('recurring_products', []);

        // Converte gli ID in array
        $productIds = is_array($productIds) ? $productIds : explode(',', $productIds);
        $recurringProductIds = is_array($recurringProductIds) ? $recurringProductIds : explode(',', $recurringProductIds);

        // Recupera i prodotti dal database
        $products = Product::whereIn('id', $productIds)->get();
        $recurringProducts = RecurringProduct::whereIn('id', $recurringProductIds)->get();

        // Verifica se ci sono prodotti da includere
        if ($products->isEmpty() && $recurringProducts->isEmpty()) {
            return redirect()->back()->with('error', 'Nessun prodotto selezionato.');
        }

        // Genera il PDF
        $pdf = Pdf::loadView('pdf.products', [
            'products' => $products,
            'recurringProducts' => $recurringProducts,
        ]);

        // Restituisce il PDF per il download
        return $pdf->download('Lista_Prodotti.pdf');
    }
}
