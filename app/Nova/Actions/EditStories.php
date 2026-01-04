<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Tag as novaTag;
use App\Enums\StoryType;
use App\Nova\Tag;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

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
            // Status non può essere modificato tramite questa azione - usare ChangeStatus invece
            if (isset($fields['creator'])) {
                $model->creator_id = $fields['creator'];
            }
            if (isset($fields['assigned_to'])) {
                $model->user_id = $fields['assigned_to'];
            }
            if (isset($fields['tester'])) {
                $model->tester_id = $fields['tester'];
            }

            if (isset($fields['tags']) && !empty($fields['tags'])) {
                $model->tags()->sync($fields['tags']);
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
        $storyTypeOptions =
            collect(StoryType::cases())->mapWithKeys(fn($type) => [$type->value => $type]);
        return [
            Select::make('Type')->options($storyTypeOptions),
            Select::make('Assigned To')->options(User::whereJsonDoesntContain('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
            Select::make('Tester')->options(User::whereJsonDoesntContain('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
            Select::make('Creator')->options(User::whereJsonContains('roles', UserRole::Customer)->get()->pluck('name', 'id'))->nullable(),
            MultiSelect::make('Tags', 'tags')
                ->options(\App\Models\Tag::all()->pluck('name', 'id'))
                ->placeholder('Seleziona i tag...')
                ->nullable(),
        ];
    }

    public function name()
    {
        return __('Edit');
    }
}
