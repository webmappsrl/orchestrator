<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;

class QuotePublicController extends Controller
{
    /**
     * Public, signature-verified PDF download (no auth:sanctum).
     * The `signed` middleware rejects the request before this method runs
     * if the signature/expiry is invalid.
     *
     * Read-only: this route is reachable by anyone holding the signed link
     * (anonymous, unlimited-IP audience), so it must never write to the
     * database — `persist: false` skips the translation cleanup/save that
     * the authenticated PDF routes perform.
     */
    public function show(Request $request, Quote $quote, QuotePdfService $pdfService)
    {
        $lang = $request->get('lang', 'it');

        return $pdfService->stream($quote, $lang, persist: false);
    }
}
