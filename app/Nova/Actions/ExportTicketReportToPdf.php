<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Models\Story;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ExportTicketReportToPdf extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Esporta Report PDF');
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $stories = $models->sortBy('created_at');

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

        // Restituisce il PDF per il download usando un response diretto
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
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

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }

    /**
     * Indicate that this action can be run without any models.
     * This allows running the action on all filtered results.
     *
     * @return bool
     */
    public function standalone()
    {
        return true;
    }
}
