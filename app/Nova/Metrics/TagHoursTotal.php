<?php

namespace App\Nova\Metrics;

use App\Models\Tag;
use InvalidArgumentException;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TagHoursTotal extends Value
{
    public function __construct(
        private readonly string $mode
    ) {
        if (!in_array($mode, ['estimated', 'effective'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid TagHoursTotal mode: %s', $mode));
        }
    }

    /**
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $tag = $request->findModel();

        if (! $tag instanceof Tag) {
            return $this->hoursResult(0.0);
        }

        $total = match ($this->mode) {
            'estimated' => round((float) ($tag->estimate ?? 0), 2),
            'effective' => (float) ($tag->getTotalHoursAttribute() ?? 0),
        };

        return $this->hoursResult($total);
    }

    public function uriKey()
    {
        return match ($this->mode) {
            'estimated' => 'tag-estimated-hours-total',
            'effective' => 'tag-effective-hours-total',
        };
    }

    public function name()
    {
        return match ($this->mode) {
            'estimated' => __('Total Estimated Hours'),
            'effective' => __('Total Effective Hours'),
        };
    }

    private function hoursResult(float $value)
    {
        return $this->precision(2)->result($value)->suffix(__('h'))->allowZeroResult();
    }
}
