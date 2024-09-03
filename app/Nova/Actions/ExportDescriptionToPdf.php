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

class ExportDescriptionToPdf extends Action
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

            $title = str_replace(' ', '', $model->name);
            $fileName = "{$title}.pdf";
            $filePath = storage_path("app/public/{$fileName}");


            $html =
                '<div style="margin: 20px; padding: 20px;">'
                . $description .
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
}
