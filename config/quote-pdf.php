<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Quote PDF Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for quote PDF generation.
    | These settings control header, footer and PDF margins.
    |
    */

    'header' => [
        'height' => env('QUOTE_PDF_HEADER_HEIGHT', 90), // in points (pt)
        'margin_top' => env('QUOTE_PDF_HEADER_MARGIN_TOP', 15), // in mm
    ],

    'footer' => [
        'height' => env('QUOTE_PDF_FOOTER_HEIGHT', 0), // in points (pt)
        'margin_bottom' => env('QUOTE_PDF_FOOTER_MARGIN_BOTTOM', 0), // in mm
    ],

    'page' => [
        'size' => env('QUOTE_PDF_PAGE_SIZE', 'a4'),
        'orientation' => env('QUOTE_PDF_PAGE_ORIENTATION', 'portrait'),
        'margin_top' => env('QUOTE_PDF_MARGIN_TOP', 15), // in mm
        'margin_bottom' => env('QUOTE_PDF_MARGIN_BOTTOM', 15), // in mm
        'margin_left' => env('QUOTE_PDF_MARGIN_LEFT', 10), // in mm
        'margin_right' => env('QUOTE_PDF_MARGIN_RIGHT', 10), // in mm
    ],

    'company' => [
        'name' => env('QUOTE_PDF_COMPANY_NAME', 'Webmapp S.r.l.'),
        'address' => env('QUOTE_PDF_COMPANY_ADDRESS', 'Via Antonio Cei - 56123 Pisa'),
        'vat' => env('QUOTE_PDF_COMPANY_VAT', 'CF/P.iva 02266770508'),
        'phone' => env('QUOTE_PDF_COMPANY_PHONE', 'Tel +39 3285360803'),
        'website' => env('QUOTE_PDF_COMPANY_WEBSITE', 'www.webmapp.it'),
        'email' => env('QUOTE_PDF_COMPANY_EMAIL', 'info@webmapp.it'),
    ],
];
