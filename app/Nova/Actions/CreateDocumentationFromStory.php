<?php

namespace App\Nova\Actions;

use App\Models\Documentation;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class CreateDocumentationFromStory extends Action
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
        $documentation = new Documentation();
        $documentation->name = $fields->name;
        $documentation->description = $fields->description;
        $documentation->save();

        return Action::openInNewTab('/resources/documentations/' . $documentation->id . '/edit');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        try {
            $model = $request->findModelOrFail();
        } catch (\Exception $e) {
            $model = null;
        }
        try {
            if (!$model) {
                throw new \Exception('Model not found');
            }
            return [
                Text::make('Title', 'name')
                    ->default($model->name)
                    ->rules('required', 'string', 'max:255'),
                Textarea::make('Description')
                    ->default($model->customer_request)
                    ->rules('required', 'string'),
            ];
        } catch (\Exception $e) {
            // Log the exception for deugging
            Log::error('Error fetching model or model not found', ['exception' => $e]);
            return [
                Text::make('Title', 'name')
                    ->rules('required', 'string', 'max:255'),
                Textarea::make('Description')
                    ->rules('required', 'string'),
            ];
        }
    }

    /**
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Create Documentation From Story');
    }
}
