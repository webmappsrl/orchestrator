<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Metrics\Partition;
use Illuminate\Http\Request;

class DynamicPartitionMetric extends Partition
{
    /**
     * Il modello su cui basare la metrica.
     *
     * @var string
     */
    public $model;

    /**
     * Il campo da analizzare.
     *
     * @var string
     */
    public $field;

    /**
     * L'etichetta della metrica.
     *
     * @var string
     */
    public $metricLabel;

    /**
     * Costruttore della metrica.
     *
     * @param string $model
     * @param string $field
     * @param string $metricLabel
     */
    public function __construct(string $model, string $field, string $metricLabel)
    {
        $this->model = $model;
        $this->field = $field;
        $this->metricLabel = __($metricLabel);

        parent::__construct();
    }

    /**
     * Calcola la metrica per l'intervallo di tempo fornito.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(Request $request)
    {
        $modelClass = $this->model;
        $field = $this->field;

        $data = $modelClass::query()
            ->select($field)
            ->selectRaw('count(*) as count')
            ->groupBy($field)
            ->pluck('count', $field)
            ->toArray();

        // Applica la traduzione alle chiavi
        $translatedData = [];
        foreach ($data as $key => $value) {
            $translatedKey = __($key);
            $translatedData[$translatedKey] = $value;
        }

        return $this->result($translatedData);
    }

    /**
     * Imposta il nome della metrica.
     *
     * @return string
     */
    public function name()
    {
        return $this->metricLabel;
    }

    /**
     * Definisce la durata per la metrica (es. All Time).
     *
     * @return array
     */
    public function ranges()
    {
        return [
            30 => __('30 Days'),
            60 => __('60 Days'),
            365 => __('365 Days'),
            'TODAY' => __('Today'),
            'ALL' => __('All Time'),
        ];
    }

    /**
     * Indica se la metrica può essere scaricata.
     *
     * @return string
     */
    public function uriKey()
    {
        return strtolower(class_basename($this)) . '-' . strtolower($this->field);
    }
}
