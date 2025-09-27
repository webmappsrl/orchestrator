<?php

namespace App\Nova\Actions;

use App\Models\FundraisingOpportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateFundraisingOpportunityFromJson extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     */
    public $name = 'Crea da JSON';

    /**
     * Indicates if this action is only available on the resource's index.
     */
    public $onlyOnIndex = true;

    /**
     * The text to be used for the action's confirm button.
     */
    public $confirmButtonText = 'Crea FRO';

    /**
     * The text to be used for the action's confirmation text.
     */
    public $confirmText = 'Sei sicuro di voler creare questa FRO dai dati JSON?';

    /**
     * Perform the action on the given models.
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $jsonData = $fields->json_data;
        
        try {
            // Decodifica il JSON
            $data = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return Action::danger('JSON non valido: ' . json_last_error_msg());
            }

            // Validazione campi obbligatori
            $requiredFields = ['name', 'deadline'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return Action::danger("Campo obbligatorio mancante: {$field}");
                }
            }

            // Mappa i campi del JSON ai campi del modello
            $mappedData = [
                'name' => $data['name'],
                'official_url' => $data['official_url'] ?? null,
                'endowment_fund' => isset($data['endowment_fund']) ? (float)$data['endowment_fund'] : null,
                'deadline' => $data['deadline'],
                'program_name' => $data['program_name'] ?? null,
                'sponsor' => $data['sponsor'] ?? null,
                'cofinancing_quota' => isset($data['cofinancing_quota']) ? (float)$data['cofinancing_quota'] : null,
                'max_contribution' => isset($data['max_contribution']) ? (float)$data['max_contribution'] : null,
                'territorial_scope' => $data['territorial_scope'] ?? 'national',
                'beneficiary_requirements' => $data['beneficiary_requirements'] ?? null,
                'lead_requirements' => $data['lead_requirements'] ?? null,
                'created_by' => auth()->id(),
                'responsible_user_id' => auth()->id(),
            ];

            // Crea la FRO
            $fro = FundraisingOpportunity::create($mappedData);

            return Action::message("FRO '{$fro->name}' creata con successo!")
                         ->redirect('/resources/fundraising-opportunities/' . $fro->id);

        } catch (\Exception $e) {
            return Action::danger('Errore durante la creazione: ' . $e->getMessage());
        }
    }

    /**
     * Get the fields available on the action.
     */
    public function fields(NovaRequest $request)
    {
        return [
            Code::make('JSON Data', 'json_data')
                ->language('json')
                ->height(300)
                ->help('Incolla il JSON con i dati della FRO da creare')
                ->rules('required')
                ->placeholder('{
  "name": "Avviso 2/2025 - Finanziamento progetti di rilevanza nazionale ETS",
  "official_url": "https://www.lavoro.gov.it/temi-e-priorita/terzo-settore-e-responsabilita-sociale-delle-imprese/focus/riforma-terzo-settore/pagine/avviso-2-2025",
  "endowment_fund": 13537043.39,
  "deadline": "2025-10-28T15:00:00",
  "program_name": "Iniziative e Progetti di Rilevanza Nazionale ai sensi dell\'articolo 72 del D.Lgs. 3 luglio 2017, n. 117",
  "sponsor": "Ministero del Lavoro e delle Politiche Sociali",
  "cofinancing_quota": 20,
  "max_contribution": 500000.00
}'),
        ];
    }
}
