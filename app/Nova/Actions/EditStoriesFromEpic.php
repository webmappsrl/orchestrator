<?php

namespace App\Nova\Actions;

use App\Models\Project;
use App\Models\Deadline;
use App\Enums\StoryStatus;
use App\Enums\StoryPriority;
use App\Enums\DeadlineStatus;
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
            if (isset($fields['creator'])) {
                $model->creator_id = $fields['creator']->id;
            }
            if (isset($fields['user'])) {
                $model->user_id = $fields['user']->id;
            }
            if (isset($fields['deadlines']) && !empty($fields['deadlines'])) {
                $model->deadlines()->sync($fields['deadlines']);
            }
            if (isset($fields['project'])) {
                $model->project_id = $fields['project'];
            }
            if (isset($fields['priority'])) {
                $model->priority = $fields['priority'];
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
            BelongsTo::make('Assigned To', 'user', 'App\Nova\User')->nullable(),
            BelongsTo::make('Creator', 'creator', 'App\Nova\User')->nullable(),
            //create a multiselect field to display all the deadlines due_date plus the related customer name as option
            MultiSelect::make('Deadlines')
                ->options(
                    function () {
                        $deadlines = Deadline::whereNotIn('status', [DeadlineStatus::Expired, DeadlineStatus::Done])->get();
                        $options = [];
                        //order the not expired deadlines by descending due date
                        $deadlines = $deadlines->sortByDesc('due_date');
                        foreach ($deadlines as $deadline) {
                            if (isset($deadline->customer) && $deadline->customer != null) {
                                $customer = $deadline->customer;
                                //format the due_date
                                $formattedDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                                //add the customer name to the option label
                                $optionLabel = $formattedDate . '    ' . $customer->name . ' ' . $deadline->title;
                            } else {
                                $formattedDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                                $optionLabel = $formattedDate . '    ' . $deadline->title;
                            }
                            $options[$deadline->id] = $optionLabel;
                        }
                        return $options;
                    }
                )->displayUsingLabels(),
            Select::make('Project')->options(Project::all()->pluck('name', 'id'))
                ->displayUsingLabels()
                ->searchable(),
            Select::make('Priority', 'priority')->options([
                StoryPriority::Low->value => 'Low',
                StoryPriority::Medium->value => 'Medium',
                StoryPriority::High->value => 'High',
            ])->default($this->priority ?? StoryPriority::Low->value),
        ];
    }

    public function name()
    {
        return 'Edit';
    }
}
