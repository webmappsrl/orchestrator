<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Documentation;

class DocumentationPdfController extends Controller
{
    public function download(Request $request, $id)
    {
        // Recupera la documentazione dal database
        $documentation = Documentation::findOrFail($id);

        $description = $documentation->description;
        $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
        $description = str_replace('<img', '<img style="max-width: 100%; height: auto;max-height:300px"', $description);
        // Aggiungi uno stile inline per i blocchi <pre> per gestire la lunghezza delle righe
        $description = str_replace('<pre><code>', '<pre style="white-space: pre-wrap; word-wrap: break-word;"><code>', $description);

        $description = '<div style="padding: 15px 0;">' . $description . '</div>';
        $title = $documentation->name;
        $pdfTitle = str_replace(' ', '', $title);
        
        // Recupera il path del logo dalla configurazione
        $logoPath = config('orchestrator.pdf_logo_path');
        
        // Genera il logo HTML (in base64 se esiste, altrimenti vuoto)
        $logoHtml = '';
        if ($logoPath && file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoExtension = pathinfo($logoPath, PATHINFO_EXTENSION);
            $logoMimeType = 'image/' . ($logoExtension === 'jpg' || $logoExtension === 'jpeg' ? 'jpeg' : strtolower($logoExtension));
            $logoBase64 = 'data:' . $logoMimeType . ';base64,' . $logoData;
            $logoHtml = '<img style="width: 115px; height: auto; margin-right: 20px;" src="' . $logoBase64 . '" alt="logo">';
        }
        
        $style = '
        <style>
            @page {
            margin: 120px 50px 80px 50px;
            }

            .header {
                display: flex;
                justify-content: flex-end;
                align-items: flex-start;
                position: fixed;
                top: -80px;
                left: 0;
                right: 0;
                width: 100%;
                text-align: right;
            }
        
            .footer {
                position: fixed;
                bottom: -50px;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 12px;
                color: #777;
            }

            .content {
                margin-top: 20px;
                margin-bottom: 20px;
            }

            h1 {
                text-align: center;
            }
        </style>';

        $header = '
        <div class="header">
                ' . $logoHtml . '
        </div>';

        // Recupera il footer dalla configurazione
        $footerText = config('orchestrator.pdf_footer');
        
        $footer = '
        <div class="footer">
            <p>' . $footerText . '</p>
        </div>';

        $html = '
        <html>
        <head>
            ' . $style . '
        </head>
        <body>
            ' . $header . '
            ' . $footer . '
            <h1>' . $title . '</h1>
            <div class="content">' . $description . '</div>
        </body>
        </html>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        // Restituisce il PDF per il download
        return $pdf->download("{$pdfTitle}.pdf");
    }
}

