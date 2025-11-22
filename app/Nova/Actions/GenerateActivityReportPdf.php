<?php

namespace App\Nova\Actions;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class GenerateActivityReportPdf extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Generate PDF';

    /**
     * Indicates if this action is only available on the resource detail page.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * Indicates if this action is available on the resource's detail view.
     *
     * @var bool
     */
    public $showOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $activityReport = $models->first();

        // Check if there are associated stories
        if ($activityReport->stories()->count() === 0) {
            return Action::danger(__('No tickets associated with this report. Please wait for tickets to be synced or check your report settings.'));
        }

        // Redirect to controller route that will generate and save the PDF
        $url = route('activity-report.pdf.generate', ['id' => $activityReport->id]);

        return Action::redirect($url);
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

    /**
     * Determine if the action is visible for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToSee($request)
    {
        if ($request->user() == null) {
            return false;
        }
        return $request->user()->hasRole(UserRole::Admin);
    }

    /**
     * Determine if the action is executable for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     * @return bool
     */
    public function authorizedToRun($request, $model = null)
    {
        if ($request->user() == null) {
            return false;
        }

        // Check if user is admin
        if (!$request->user()->hasRole(UserRole::Admin)) {
            return false;
        }

        // Check if there are associated stories (only if model is provided)
        if ($model && $model->stories()->count() === 0) {
            return false;
        }

        return true;
    }
}

