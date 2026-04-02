<?php

namespace App\Nova\Actions;

use App\Exports\SelectedStoriesToExcel;
use App\Models\Tag;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
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

        $date = now()->format('Ymd');
        $tagName = null;

        $request = app(NovaRequest::class);
        $viaResource = $request->get('viaResource');
        $viaResourceId = $request->get('viaResourceId');
        if ($viaResource === 'tags' && $viaResourceId) {
            $tag = Tag::find($viaResourceId);
            $tagName = $tag?->name;
        }

        $safeTagName = $tagName
            ? trim((string) preg_replace('/[^\pL\pN]+/u', '_', $tagName), '_')
            : 'Tag';

        $fileName = "TagReport_{$safeTagName}_{$date}.xls";

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
        return __('Export Selected Tickets (Excel)');
    }
}

