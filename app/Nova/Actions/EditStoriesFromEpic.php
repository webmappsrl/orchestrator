<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Enums\EpicStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class EditStoriesFromEpic extends Action
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
            if (isset($fields['status'])) {
                $model->status = $fields['status'];
            }
            if (isset($fields['user'])) {
                $model->user_id = $fields['user']->id;
            }
            if (isset($fields['milestone'])) {
                $model->milestone_id = $fields['milestone']->id;
            }
            if (isset($fields['project'])) {
                $model->project_id = $fields['project']->id;
            }
            $model->save();
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
        $statusOptions = [];
        foreach (EpicStatus::cases() as $value) {
            $statusOptions[$value->name] = $value->value;
        }

        return [
            Select::make('Status')->options($statusOptions),
            BelongsTo::make('User')->nullable(),
            BelongsTo::make('Milestone')->nullable(),
            BelongsTo::make('Project')->nullable(),
        ];
    }
}