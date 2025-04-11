<?php

namespace App\Nova\Metrics;

use App\Models\Tag;
use App\Models\Story;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class TagSal  extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $requestModel = $request->findModel();

        $estimatedTime = $requestModel->estimate;
        $actualTime = $this->getEstimatedTime($requestModel);

        return $this->result([
            __('actual') => $actualTime,
            __('estimated')  => $estimatedTime
        ])->colors($this->colors());
    }
    public function name()
    {
        return __('SAL'); // Sostituisci con il titolo desiderato
    }

    private function getEstimatedTime($requestModel)
    {
        if ($requestModel instanceof Tag) {
            // Recupera il tag e le storie associate
            $totalHours = $requestModel->getTotalHoursAttribute(); // Somma delle ore delle storie associate
            return $totalHours; // Calcola la percentuale di avanzamento
        }

        return 0; // Restituisci 0 se non è un tag
    }

    public function colors()
    {
        return [
            __('actual') => '#28A745', // Colore verde per il tempo attuale
            __('estimated') => '#DC3545', // Colore rosso per il tempo stimato
        ];
    }
}
