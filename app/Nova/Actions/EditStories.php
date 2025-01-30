<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Enums\UserRole;
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
use App\Enums\StoryType;
class EditStories extends Action
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
            if (isset($fields['type'])) {
                $model->type = $fields['type'];
            }   
            if (isset($fields['status'])) {
                $model->status = $fields['status'];
            }
            if (isset($fields['creator'])) {
                $model->creator_id = $fields['creator'];
            }
            if (isset($fields['assigned_to'])) {
                $model->user_id = $fields['assigned_to'];
            }
            if (isset($fields['tester'])) {
                $model->tester_id = $fields['tester'];
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
        $storyStatusOptions =
            collect(StoryStatus::cases())->mapWithKeys(fn($status) => [$status->value => $status]);
        $storyTypeOptions = 
            collect(StoryType::cases())->mapWithKeys(fn($type) => [$type->value => $type]);
        return [
            Select::make('Type')->options($storyTypeOptions),
            Select::make('Status')->options($storyStatusOptions),
            Select::make('Assigned To')->options(User::whereJsonDoesntContain('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
            Select::make('Tester')->options(User::whereJsonDoesntContain('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
            Select::make('Creator')->options(User::whereJsonContains('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
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
        return __('Edit');
    }
}
