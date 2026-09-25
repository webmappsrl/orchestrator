<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\QuoteController;
use App\Models\Quote;
use App\Rules\AdditionalServicesMap;
use App\Services\Quotes\RichTextHtmlInspector;
use Illuminate\Console\Command;

class CheckQuoteRichText extends Command
{
    protected $signature = 'quotes:check-rich-text';

    protected $description = 'Sola lettura: elenca i preventivi i cui campi rich-text o servizi aggiuntivi
                              verrebbero rifiutati dalla validazione API (oc:8631). Da lanciare in produzione
                              prima del rilascio: exit code 1 se trova qualcosa.';

    public function handle(RichTextHtmlInspector $inspector): int
    {
        $rows = [];

        Quote::query()->orderBy('id')->each(function (Quote $quote) use ($inspector, &$rows) {
            foreach (QuoteController::RICH_TEXT_FIELDS as $field) {
                foreach ($quote->getTranslations($field) as $locale => $html) {
                    if (! is_string($html) || $html === '') {
                        continue;
                    }
                    $problems = [];
                    if (mb_strlen($html) > 50000) {
                        $problems[] = 'lunghezza ' . mb_strlen($html);
                    }
                    foreach ($inspector->inspect($html) as $v) {
                        $problems[] = "{$v['kind']} {$v['name']} ×{$v['count']}";
                    }
                    if ($problems !== []) {
                        $rows[] = [$quote->id, "{$field} [{$locale}]", implode(', ', $problems)];
                    }
                }
            }

            foreach ($quote->getTranslations('additional_services') as $locale => $services) {
                if (! is_array($services) || $services === []) {
                    continue;
                }
                if (array_is_list($services)) {
                    $rows[] = [$quote->id, "additional_services [{$locale}]", 'lista invece di oggetto'];
                    continue;
                }
                $bad = collect($services)->reject(fn ($price) => AdditionalServicesMap::isValidPrice($price))
                    ->map(fn ($price, $service) => $service . ' = ' . json_encode($price, JSON_UNESCAPED_UNICODE));
                if ($bad->isNotEmpty()) {
                    $rows[] = [$quote->id, "additional_services [{$locale}]", $bad->implode(', ')];
                }
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
}
