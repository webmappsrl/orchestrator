<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportToPdf extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            $description = $model->description;
            $description = mb_convert_encoding($description, 'UTF-8', 'UTF-8');
            $description = str_replace('<img', '<img style="max-width: 100%; height: auto;max-height:300px"', $description);
            // Aggiungi uno stile inline per i blocchi <pre> per gestire la lunghezza delle righe
            $description = str_replace('<pre><code>', '<pre style="white-space: pre-wrap; word-wrap: break-word;"><code>', $description);

            $description = '<div style="padding: 15px 0;">' . $description . '</div>';
            $title = str_replace(' ', '', $model->name);
            $fileName = "{$title}.pdf";
            $filePath = storage_path("app/public/{$fileName}");
            $imagePath = public_path('/images/logo-orizzontale.png');
            $header = '
            <header style="padding-bottom: 20px; text-align: right; width: 100%; top: 0; display: flex; flex-direction: column; align-items: flex-start; justify-content: flex-end;">
                    <img style="width: 115px; height: auto; margin-right: 20px;" src="' . $imagePath . '" alt="webmapp logo">
            </header>';

            $footer = '
            <footer style="text-align: center; padding-top: 20px; font-size: 14px; color: #777; position: fixed; bottom: 0; left: 0; right: 0;">
                <div>
                    <p>Webmapp S.r.l. - Via Antonio Cei, 2 - 56123 Pisa <br>
                    C.F. / P. IVA: 02266770508 - Tel. +39 328 5360803 <br>
                    www.webmapp.it | info@webmapp.it</p>
                </div>
            </footer>';

            $html =
                '<div style="margin: 20px; padding: 20px;">'
                . $header
                . $description
                . $footer .
                '</div>';
            $pdf = Pdf::loadHTML($html);

            file_put_contents($filePath, $pdf->output());
            $downloadUrl = asset("storage/{$fileName}");
            return ActionResponse::download($fileName, $downloadUrl);
        }
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
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Export To Pdf');
    }
}
