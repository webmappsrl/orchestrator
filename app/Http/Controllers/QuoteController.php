<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class QuoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreQuoteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $quote = Quote::findOrFail($id);
        $quote->clearEmptyAdditionalServicesTranslations(); // necessary for the fallback locale to work, otherwise will return a language key with empty string
        $lang = $request->get('lang', 'it');

        App::setLocale($lang);

        // Always generate PDF with DomPDF
        return $this->generatePdf($quote, $lang);
    }

    /**
     * Generate PDF for the quote using DomPDF
     */
    protected function generatePdf(Quote $quote, string $lang)
    {
        $config = config('quote-pdf');
        $customerName = $quote->customer->full_name ?? $quote->customer->name;
        $pdfName = __('Preventivo_WEBMAPP_' . $customerName);

        // Generate PDF using DomPDF with custom configuration
        $pdf = Pdf::loadView('quote-pdf', compact('quote', 'config'))
            ->setPaper($config['page']['size'], $config['page']['orientation'])
            ->setOption('enable-local-file-access', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream($pdfName . '.pdf');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quote $quote)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateQuoteRequest $request, Quote $quote)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quote $quote)
    {
        //
    }
}
