<?php

namespace App\Nova\Actions;

use App\Models\Deadline;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;

class EditStoriesFromEpic extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Edit Story';

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
            if (isset($fields['deadlines'])) {
                $model->deadlines()->sync($fields['deadlines']);
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
        return [
            Select::make('Status')->options(collect(StoryStatus::cases())->pluck('name', 'value'))->displayUsingLabels(),
            BelongsTo::make('User')->nullable(),
            //create a multiselect field to display all the deadlines due_date plus the related customer name as option
            MultiSelect::make('Deadlines')
                ->options(Deadline::all()->mapWithKeys(function ($deadline) {
                    if (isset($deadline->customer) && $deadline->customer != null) {
                        $customer = $deadline->customer;
                        $formattedDate = Carbon::parse($deadline->due_date)->format('d-m-Y');
                        $optionLabel = $formattedDate . '    ' . $customer->name;
                    } else {
                        $formattedDate = Carbon::parse($deadline->due_date)->format('d-m-Y');
                        $optionLabel = $formattedDate;
                    }
                    return [$deadline->id => $optionLabel];
                })->toArray())
                ->displayUsingLabels(),
        ];
    }

    public function name()
    {
        return 'Edit';
    }
}
