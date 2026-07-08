<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAppReportJob;
use App\Models\App;
use Illuminate\Support\Facades\Cache;

class AppReportController extends Controller
{
    public function download(int $id)
    {
        $app = App::findOrFail($id);

        // Report solo per app pubblicate sugli store (oc:8242): senza store
        // presence il lookup non produce dati e il PDF uscirebbe vuoto.
        if (! $app->hasStorePresence()) {
            return response(
                '<h2>' . e(__('Report not available')) . '</h2><p>'
                . e(__('This app has no store links configured: there is no store data to report. Add the App Store / Play Store links to the app to enable the report.'))
                . '</p>',
                422
            );
        }

        $path = $this->pdfPath($app);

        // File già pronto: servi subito
        if (file_exists($path)) {
            return response()->download($path, basename($path));
        }

        // Job già in coda: mostra pagina di attesa
        $cacheKey = "app_report_generating_{$id}";
        if (Cache::has($cacheKey)) {
            return $this->waitingResponse($app, $id);
        }

        // Dispatch job e mostra pagina di attesa
        Cache::put($cacheKey, true, now()->addMinutes(10));
        GenerateAppReportJob::dispatch($app->id, $app->name, $this->bundleId($app), $path)->onQueue('reports');

        return $this->waitingResponse($app, $id);
    }

    private function bundleId(App $app): ?string
    {
        return $app->storeBundleId();
    }

    private function pdfPath(App $app): string
    {
        return $app->reportPdfPath();
    }

    private function waitingResponse(App $app, int $id): \Illuminate\Http\Response
    {
        $refreshUrl = route('app.report.download', ['id' => $id]);
        $appName    = e($app->name);

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="it">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="refresh" content="8; url={$refreshUrl}">
            <title>Generazione Report — {$appName}</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    margin: 0;
                    background: #f3f4f6;
                }
                .box {
                    text-align: center;
                    padding: 2.5rem 3rem;
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
                    max-width: 420px;
                }
                .spinner {
                    width: 44px;
                    height: 44px;
                    border: 4px solid #e5e7eb;
                    border-top-color: #79c35b;
                    border-radius: 50%;
                    animation: spin 0.9s linear infinite;
                    margin: 0 auto 1.5rem;
                }
                @keyframes spin { to { transform: rotate(360deg); } }
                h2 { margin: 0 0 0.5rem; font-size: 1.25rem; color: #111827; }
                p  { margin: 0.4rem 0; color: #6b7280; font-size: 0.9rem; }
                a  { color: #79c35b; text-decoration: none; font-size: 0.85rem; }
                a:hover { text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="box">
                <div class="spinner"></div>
                <h2>Generazione Report in corso</h2>
                <p><strong>{$appName}</strong></p>
                <p>Il PDF viene generato con i dati da App Store e Google Play.</p>
                <p>La pagina si aggiornerà automaticamente ogni 8 secondi.</p>
                <br>
                <a href="{$refreshUrl}">↻ Aggiorna ora</a>
            </div>
        </body>
        </html>
        HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
