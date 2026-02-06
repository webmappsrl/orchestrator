<?php

namespace App\Nova\Actions;

use App\Enums\CustomerStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class EditCustomerStatus extends Action
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
                $model->save();
            }
        }

        return Action::message('Status changed correctly');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $customerStatusOptions =
            collect(CustomerStatus::cases())->mapWithKeys(function ($status) {
                return [$status->value => __($status->name)];
            })->toArray();
        return [
            Select::make('Status')->options($customerStatusOptions),
        ];
    }

    /**
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Edit Status');
    }
}
