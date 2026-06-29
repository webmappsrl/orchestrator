<?php

namespace App\Nova\Actions;

use App\Enums\UserRole;
use App\Jobs\GeneratePerformanceReportJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class GeneratePerformanceReportAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Genera Report Performance';

    public function handle(ActionFields $fields, Collection $models): void
    {
        $year             = (int) $fields->year;
        $quarter          = (int) $fields->quarter;
        $requestedByUserId = auth()->id();

        foreach ($models as $user) {
            if (!$user->hasRole(UserRole::Developer)) {
                continue;
            }
            GeneratePerformanceReportJob::dispatch($user->id, $year, $quarter, $requestedByUserId);
        }
    }

    public function fields(NovaRequest $request): array
    {
        $currentYear    = Carbon::now()->year;
        $currentQuarter = (int) ceil(Carbon::now()->month / 3);

        return [
            Select::make('Quarter', 'quarter')
                ->options([1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'])
                ->default($currentQuarter)
                ->rules('required'),
            Number::make('Anno', 'year')
                ->default($currentYear)
                ->min(2020)
                ->max($currentYear)
                ->rules('required', 'integer'),
        ];
    }
}
