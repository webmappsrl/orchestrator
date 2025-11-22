<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Story;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Carbon\Carbon;

class TicketReportPdfController extends Controller
{
    public function download(Request $request)
    {
        // Applica gli stessi filtri della indexQuery di TicketReport
        $query = Story::query()
            ->where('status', StoryStatus::Done->value)
            ->where('type', '!=', StoryType::Scrum->value);

        // Applica filtri opzionali
        if ($request->has('creator_id') && $request->creator_id) {
            $query->where('creator_id', $request->creator_id);
        }

        if ($request->has('organization_id') && $request->organization_id) {
            $query->whereHas('creator.organizations', function ($q) use ($request) {
                $q->where('organizations.id', $request->organization_id);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtri per range date su done_at
        if ($request->has('done_at_start') && $request->done_at_start) {
            $query->whereNotNull('done_at')
                ->whereDate('done_at', '>=', $request->done_at_start);
        }

        if ($request->has('done_at_end') && $request->done_at_end) {
            $query->whereNotNull('done_at')
                ->whereDate('done_at', '<=', $request->done_at_end);
        }

        $stories = $query->orderBy('created_at', 'asc')->get();

        // Prepara i dati per il PDF
        $data = [
            'stories' => $stories,
            'title' => 'Report Attività Ticket',
            'generated_at' => Carbon::now()->format('d/m/Y H:i'),
        ];

        // Genera l'HTML per la tabella
        $html = $this->generatePdfHtml($data);

        // Genera il PDF
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        // Nome del file
        $filename = 'report-tickets-' . Carbon::now()->format('Y-m-d_His') . '.pdf';

        // Restituisce il PDF per il download
        return $pdf->download($filename);
    }

    /**
     * Genera l'HTML per il PDF
     *
     * @param array $data
     * @return string
     */
    private function generatePdfHtml(array $data): string
    {
        $logoPath = config('orchestrator.pdf_logo_path');
        $logoHtml = '';
        
        if ($logoPath && file_exists($logoPath)) {
            try {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoExtension = pathinfo($logoPath, PATHINFO_EXTENSION);
                $logoMimeType = 'image/' . ($logoExtension === 'jpg' || $logoExtension === 'jpeg' ? 'jpeg' : strtolower($logoExtension));
                $logoBase64 = 'data:' . $logoMimeType . ';base64,' . $logoData;
                $logoHtml = '<img style="width: 115px; height: auto; margin-right: 20px;" src="' . $logoBase64 . '" alt="logo">';
            } catch (\Exception $e) {
                // Continua senza logo
            }
        }

        $footerText = config('orchestrator.pdf_footer', '');

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
            }

            h1 {
                text-align: center;
                font-size: 24px;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                font-size: 10px;
                margin-top: 10px;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2;
                font-weight: bold;
            }

            tr:nth-child(even) {
                background-color: #f9f9f9;
            }

            .description {
                max-width: 200px;
                word-wrap: break-word;
            }
        </style>';

        $header = '<div class="header">' . $logoHtml . '</div>';
        $footer = '<div class="footer"><p>' . htmlspecialchars($footerText) . '</p></div>';

        // Genera la tabella
        $tableRows = '';
        foreach ($data['stories'] as $story) {
            $createdAt = $story->created_at ? $story->created_at->format('d/m/Y') : '-';
            $releasedAt = $story->released_at ? $story->released_at->format('d/m/Y') : '-';
            $doneAt = $story->done_at ? $story->done_at->format('d/m/Y') : '-';
            $title = htmlspecialchars($story->name ?? '-');
            $description = htmlspecialchars(strip_tags($story->description ?? '-'));
            $description = mb_strimwidth($description, 0, 100, '...');

            $tableRows .= '
            <tr>
                <td>' . $createdAt . '</td>
                <td>' . $releasedAt . '</td>
                <td>' . $doneAt . '</td>
                <td>' . $title . '</td>
                <td class="description">' . $description . '</td>
            </tr>';
        }

        $html = '
        <html>
        <head>
            ' . $style . '
            <meta charset="utf-8">
        </head>
        <body>
            ' . $header . '
            ' . $footer . '
            <h1>' . htmlspecialchars($data['title']) . '</h1>
            <div class="content">
                <p style="text-align: right; margin-bottom: 10px;">Generato il: ' . $data['generated_at'] . '</p>
                <table>
                    <thead>
                        <tr>
                            <th>Data Creazione</th>
                            <th>Data Rilascio</th>
                            <th>Data Completato</th>
                            <th>Titolo</th>
                            <th>Richiesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $tableRows . '
                    </tbody>
                </table>
            </div>
        </body>
        </html>';

        return $html;
    }
}

