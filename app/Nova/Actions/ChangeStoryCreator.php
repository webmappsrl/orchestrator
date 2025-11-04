<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class ChangeStoryCreator extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Change Story Creator';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $customerId = $fields->customer_id;
        
        if (!$customerId) {
            return Action::danger('Please select a customer.');
        }

        $customer = User::find($customerId);
        
        if (!$customer || !$customer->hasRole(UserRole::Customer)) {
            return Action::danger('Selected user is not a customer.');
        }

        $updatedCount = 0;
        
        foreach ($models as $story) {
            $story->creator_id = $customerId;
            $story->save();
            $updatedCount++;
        }

        return Action::message("Successfully changed creator to {$customer->name} for {$updatedCount} story(ies).");
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
            Select::make('Customer', 'customer_id')
                ->options(function () {
                    return User::whereJsonContains('roles', UserRole::Customer)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->searchable()
                ->required()
                ->help('Select the customer who should be the creator of this story.')
                ->displayUsingLabels(),
        ];
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function authorizedToSee($request)
    {
        if ($request->user() == null) {
            return false;
        }
        return $request->user()->hasRole(UserRole::Developer) || $request->user()->hasRole(UserRole::Admin);
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function authorizedToRun($request, $model)
    {
        if ($request->user() == null) {
            return false;
        }
        return $request->user()->hasRole(UserRole::Developer) || $request->user()->hasRole(UserRole::Admin);
    }
}
