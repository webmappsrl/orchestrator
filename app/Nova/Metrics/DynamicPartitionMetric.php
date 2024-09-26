<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Metrics\Partition;
use Illuminate\Http\Request;

class DynamicPartitionMetric extends Partition
{


    /**
     * Il campo da analizzare.
     *
     * Questo campo è il riferimento all'interno del modello principale
     * su cui verranno effettuati i raggruppamenti e i calcoli.
     *
     * @var string
     */
    public $field;

    /**
     * L'etichetta della metrica.
     *
     * Questa è l'etichetta visibile dell'interfaccia Nova per la metrica.
     * Può essere un valore tradotto.
     *
     * @var string
     */
    public $metricLabel;

    /**
     * Il modello associato per la traduzione delle chiavi.
     *
     * Questo parametro è facoltativo. Se presente, verrà utilizzato
     * per tradurre le chiavi della metrica con i valori provenienti
     * da un altro modello. Solitamente utilizzato per mostrare nomi
     * o descrizioni al posto di ID numerici.
     *
     * @var string|null
     */
    public $fieldModel;

    /**
     * Il campo da usare per la traduzione delle chiavi.
     *
     * Questo parametro è il campo del modello associato che verrà
     * utilizzato per tradurre le chiavi della metrica. Deve essere
     * presente solo se `$fieldModel` è definito.
     *
     * @var string|null
     */
    public $fieldModelLabel;

    public $query;
    /**
     * Costruttore della metrica.
     *
     * @param string $metricLabel L'etichetta della metrica (tradotta).
     * @param string $model Il modello principale da cui recuperare i dati.
     * @param string $field Il campo su cui basare il raggruppamento e il conteggio.
     * @param string|null $fieldModel (Opzionale) Il modello per tradurre le chiavi.
     * @param string|null $fieldModelLabel (Opzionale) Il campo del modello associato per la traduzione.
     */
    public function __construct(string $metricLabel,  $query = null, string $field, string $fieldModel = null, string $fieldModelLabel = null)
    {
        $this->field = $field;
        $this->metricLabel = __($metricLabel);
        $this->fieldModel = $fieldModel;
        $this->fieldModelLabel = $fieldModelLabel;
        $this->query = $query;

        parent::__construct();
    }

    /**
     * Calcola la metrica per l'intervallo di tempo fornito.
     *
     * Questo metodo viene chiamato da Nova per generare la metrica.
     * Recupera i dati dal modello principale, effettua un conteggio
     * per il campo selezionato e poi, se definito, applica la traduzione delle chiavi.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request La richiesta Nova.
     * @return mixed Il risultato della metrica in formato array.
     */
    public function calculate(Request $request)
    {
        $field = $this->field;
        // Recupera i dati grezzi (chiavi e conteggi)
        $data =  $this->query
            ->select($field)
            ->selectRaw('count(*) as count')
            ->groupBy($field)
            ->pluck('count', $field)
            ->toArray();

        // Applica la traduzione alle chiavi, se necessario
        $translatedData = $this->translateKeys($data);

        // Ritorna i dati tradotti ordinati
        return $this->result($translatedData);
    }

    /**
     * Traduci le chiavi basate su un modello associato, se definito.
     *
     * Se viene fornito un modello associato e un campo per la traduzione delle chiavi,
     * il metodo cercherà di tradurre gli ID nelle chiavi appropriate. Se il modello o
     * il campo non sono definiti, restituirà le chiavi originali.
     *
     * @param array $data I dati originali (ID => count).
     * @return array I dati con le chiavi tradotte e ordinati per valore.
     */
    protected function translateKeys(array $data)
    {
        $translatedData = [];

        // Se è definito un modello associato e un campo per la traduzione delle chiavi
        if ($this->fieldModel && $this->fieldModelLabel) {
            $fieldModelClass = $this->fieldModel;

            // Ottieni le chiavi dai dati originali
            $keys = array_keys($data);

            // Filtra solo le chiavi che sono ID validi (numerici)
            $validKeys = array_filter($keys, function ($key) {
                return is_numeric($key); // Controlla che la chiave sia un ID numerico
            });

            // Recupera le traduzioni dal modello associato
            $translations = $fieldModelClass::whereIn('id', $validKeys)
                ->pluck($this->fieldModelLabel, 'id')
                ->toArray();

            // Mappa i dati tradotti
            foreach ($data as $key => $value) {
                // Usa la traduzione se esiste, altrimenti la chiave originale
                $translatedKey = $translations[$key] ?? __($key);
                $translatedData[$translatedKey] = $value;
            }
        } else {
            // Se non c'è un modello associato, usa i dati originali
            foreach ($data as $key => $value) {
                $translatedData[__($key)] = $value;
            }
        }

        // Ordina i risultati per valore (conteggio) in ordine decrescente
        arsort($translatedData);

        return $translatedData;
    }

    /**
     * Imposta il nome della metrica.
     *
     * Questo metodo definisce il nome visualizzato della metrica
     * nell'interfaccia di Nova, utilizzando l'etichetta fornita nel costruttore.
     *
     * @return string Il nome della metrica.
     */
    public function name()
    {
        return $this->metricLabel;
    }

    /**
     * Definisce la durata per la metrica (es. All Time).
     *
     * Questo metodo fornisce le opzioni di intervallo di tempo che possono
     * essere selezionate per la metrica, come "30 Days", "60 Days", etc.
     *
     * @return array Un array di intervalli di tempo selezionabili.
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
     * Questo metodo definisce la chiave URI per la metrica, che viene
     * utilizzata per la creazione dinamica degli endpoint di download.
     *
     * @return string La chiave URI per la metrica.
     */
    public function uriKey()
    {
        return strtolower(class_basename($this)) . '-' . strtolower($this->field);
    }
}
