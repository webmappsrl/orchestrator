<?php

namespace App\Nova\Actions;

use App\Exports\SelectedStoriesToExcel;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\Excel\Facades\Excel;

class ExportStoriesToExcel extends Action
{
    use Queueable;

    /**
     * Perform the action on the given models.
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        // Eager-load relationships to avoid N+1 queries during export mapping.
        // $models is usually an Eloquent Collection, but Nova types it as Support\Collection.
        if (method_exists($models, 'load')) {
            $models->load(['tags', 'creator', 'developer', 'tester']);
        }

        $fileName = 'tickets-' . now()->format('Ymd-His') . '-' . Str::random(8) . '.xlsx';

        Excel::store(
            new SelectedStoriesToExcel($models),
            $fileName,
            'public'
        );

        $downloadUrl = route('stories.excel.download', ['fileName' => $fileName]);

        return ActionResponse::download($fileName, $downloadUrl);
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the displayable name of the action.
     */
    public function name()
    {
        return __('Export selected tickets (Excel)');
    }
}

