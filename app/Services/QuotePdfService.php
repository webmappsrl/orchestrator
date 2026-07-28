<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class QuotePdfService
{
    /**
     * Generate and stream the quote PDF using DomPDF.
     *
     * @param bool $persist When true (default), the empty additional
     *   services translations cleanup is persisted to the database — this
     *   is a DB write (bumps `updated_at`, and cascades to a mass update of
     *   sibling quotes if this one is a template). Pass false for read-only
     *   callers (e.g. the anonymous public link) that must not write to the
     *   database; the in-memory translation normalization still runs either
     *   way, so rendering is always correct regardless of the flag.
     */
    public function stream(Quote $quote, string $lang, bool $persist = true): Response
    {
        $quote->clearEmptyAdditionalServicesTranslations($persist);
        App::setLocale($lang);

        $config = config('quote-pdf');

        $pdf = Pdf::loadView('quote-pdf', compact('quote', 'config'))
            ->setPaper($config['page']['size'], $config['page']['orientation'])
            ->setOption('enable-local-file-access', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream($this->fileName($quote));
    }

    /**
     * Build a sanitized filename for the quote PDF.
     * Customer name is free text (no validation, no cast): strip anything
     * that isn't alphanumeric/space/underscore/dash before it reaches
     * Content-Disposition, and cap the length.
     */
    public function fileName(Quote $quote): string
    {
        $customerName = $quote->customer->full_name ?? $quote->customer->name ?? 'Cliente';

        $safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', $customerName);
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));
        $safeName = substr($safeName, 0, 80);

        if ($safeName === '') {
            $safeName = 'Cliente';
        }

        return __('Preventivo_WEBMAPP_' . $safeName) . '.pdf';
    }
}
