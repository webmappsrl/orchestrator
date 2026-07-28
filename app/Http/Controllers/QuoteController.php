<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(StoreQuoteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id, QuotePdfService $pdfService)
    {
        $quote = Quote::findOrFail($id);
        $lang = $request->get('lang', 'it');

        return $pdfService->stream($quote, $lang);
    }

    public function edit(Quote $quote)
    {
        //
    }

    public function update(UpdateQuoteRequest $request, Quote $quote)
    {
        //
    }

    public function destroy(Quote $quote)
    {
        //
    }
}
