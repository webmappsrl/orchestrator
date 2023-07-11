<?php

namespace App\Nova\Actions;

use App\Enums\DeadlineStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;

class EditDeadlinesAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Edit Deadlines status';

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

            $model->status = $fields->status ?? $model->status;

            $model->save();
        }

        return Action::message('The deadlines have been updated!');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Status')->options([
                'new' => DeadlineStatus::New,
                'progress' => DeadlineStatus::Progress,
                'done' => DeadlineStatus::Done,
                'expired' => DeadlineStatus::Expired,
            ]),

        ];
    }
}