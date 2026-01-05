<?php

namespace App\Nova\Actions;

use App\Services\AutoUpdateWaitingStoriesService;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class UpdateWaitingStoriesAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Aggiorna Ticket in Attesa';

    /**
     * Indicates if this action is only available on the resource's index.
     *
     * @var bool
     */
    public $onlyOnIndex = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $service = new AutoUpdateWaitingStoriesService();
        $result = $service->updateWaitingStories($models);

        $message = sprintf(
            'Aggiornamento completato. Successo: %d, Saltati: %d, Errori: %d',
            $result['success'],
            $result['skipped'],
            $result['errors']
        );

        if ($result['errors'] > 0) {
            return Action::danger($message);
        }

        return Action::message($message);
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

