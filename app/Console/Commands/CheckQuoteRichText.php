<?php

namespace App\Console\Commands;

use App\Models\Quote;
use App\Rules\AdditionalServicesMap;
use App\Services\Quotes\QuoteRichText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

/**
 * Applica ai dati esistenti, in tutte le lingue, le stesse regole con cui
 * l'API valida i campi rich-text e i servizi aggiuntivi (oc:8631): passa dal
 * Validator con le stesse Rule, così non può divergere da ciò che l'API rifiuta.
 */
class CheckQuoteRichText extends Command
{
    protected $signature = 'quotes:check-rich-text';

    protected $description = 'Sola lettura: elenca i preventivi che la validazione API rifiuterebbe (exit 1 se ne trova). Da lanciare in produzione prima del rilascio.';

    public function handle(): int
    {
        App::setLocale('it');
        $rows = [];

        Quote::query()->orderBy('id')->each(function (Quote $quote) use (&$rows) {
            foreach (QuoteRichText::FIELDS as $field) {
                foreach ($quote->getTranslations($field) as $locale => $html) {
                    $this->check($rows, $quote, $field, $locale, $html, QuoteRichText::fieldRules());
                }
            }

            foreach ($quote->getTranslations('additional_services') as $locale => $services) {
                $this->check($rows, $quote, 'additional_services', $locale, $services, ['nullable', 'array', new AdditionalServicesMap()]);
            }
        });

        if ($rows === []) {
            $this->info('Nessun preventivo verrebbe rifiutato.');
            return self::SUCCESS;
        }

        $this->table(['Preventivo', 'Campo [lingua]', 'Motivo'], $rows);
        $this->warn(count($rows) . ' campi verrebbero rifiutati: allargare la regola prima del rilascio o correggere i dati.');

        return self::FAILURE;
    }

    private function check(array &$rows, Quote $quote, string $field, string $locale, mixed $value, array $rules): void
    {
        $validator = Validator::make([$field => $value], [$field => $rules]);
        if ($validator->fails()) {
            $rows[] = [$quote->id, "{$field} [{$locale}]", $validator->errors()->first($field)];
        }
    }
}
