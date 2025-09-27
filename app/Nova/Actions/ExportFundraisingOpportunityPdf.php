<?php

namespace App\Nova\Actions;

use App\Models\FundraisingOpportunity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ExportFundraisingOpportunityPdf extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Esporta PDF';

    /**
     * Indicates if this action is only available on the resource index.
     *
     * @var bool
     */
    public $onlyOnIndex = false;

    /**
     * Indicates if this action is available on the resource detail.
     *
     * @var bool
     */
    public $showOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $opportunity) {
            /** @var FundraisingOpportunity $opportunity */
            
            // Genera il PDF
            $pdf = Pdf::loadView('pdf.fundraising-opportunity', [
                'opportunity' => $opportunity
            ]);

            // Nome del file
            $filename = 'opportunita_' . \Str::slug($opportunity->name) . '_' . now()->format('Y-m-d') . '.pdf';

            // Fornisce il download del PDF
            return Action::download($filename, $pdf->output());
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
