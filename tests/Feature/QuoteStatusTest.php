<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use Tests\TestCase;

class QuoteStatusTest extends TestCase
{
    private string $originalLocale;

    private string $originalFallback;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLocale = app()->getLocale();
        $this->originalFallback = app('translator')->getFallback();
    }

    protected function tearDown(): void
    {
        app()->setLocale($this->originalLocale);
        app('translator')->setFallback($this->originalFallback);

        parent::tearDown();
    }

    public function test_on_hold_esiste_fra_presented_e_waiting_for_order()
    {
        $this->assertSame('on hold', QuoteStatus::On_Hold->value);

        // L'ordine dei case decide l'ordine delle colonne del Kanban Sales.
        $cases = QuoteStatus::cases();
        $position = array_search(QuoteStatus::On_Hold, $cases, true);
        $this->assertNotFalse($position, 'On_Hold non è presente fra i case');

        $this->assertSame(QuoteStatus::Presented, $cases[$position - 1]);
        $this->assertSame(QuoteStatus::Waiting_For_Order, $cases[$position + 1]);
    }

    /**
     * QuoteStatus::label() è un match ESAUSTIVO SENZA ramo default: un case aggiunto senza la
     * riga corrispondente solleva \UnhandledMatchError, cioè un 500 sul dashboard Sales e sulle
     * viste delle trattative. Questo test protegge anche gli stati futuri.
     */
    public function test_ogni_case_ha_label_e_colore()
    {
        foreach (QuoteStatus::cases() as $case) {
            $label = $case->label();
            $this->assertIsString($label);
            $this->assertNotSame('', $label, "label() vuota per il case {$case->name}");

            $this->assertMatchesRegularExpression(
                '/^#[0-9A-Fa-f]{6}$/',
                $case->color(),
                "color() non è un hex valido per il case {$case->name}"
            );
        }
    }

    public function test_on_hold_ha_il_colore_azzurro()
    {
        $this->assertSame('#0EA5E9', QuoteStatus::On_Hold->color());
    }

    /**
     * Gli stati della trattativa si traducono con tre chiavi: il nome del case (QuoteStatusFilter),
     * la stringa scritta in label() (Kanban, elenco, scheda e form) e il valore salvato nel DB
     * (card «Quotes by Status» dell'elenco, DynamicPartitionMetric traduce con __($key)).
     */
    public function test_ogni_case_ha_le_chiavi_di_traduzione_in_it_e_en()
    {
        // label() chiama già __() e restituisce il testo tradotto: con una lingua inesistente,
        // anche come lingua di riserva, restituisce la chiave grezza e il test la ricava da solo.
        // Vale finché label() usa __('stringa') senza parametri: con trans_choice, segnaposto o
        // chiavi di file PHP (quotes.status.x) questo test va riscritto.
        app()->setLocale('zz');
        app('translator')->setFallback('zz');

        $keysByCase = [];
        foreach (QuoteStatus::cases() as $case) {
            $keysByCase[$case->name] = [$case->name, $case->label(), $case->value];
        }

        foreach (['it', 'en'] as $locale) {
            $translations = json_decode(file_get_contents(base_path("lang/{$locale}.json")), true);

            foreach ($keysByCase as $caseName => $keys) {
                foreach ($keys as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $translations,
                        "manca la chiave '{$key}' (case {$caseName}) in lang/{$locale}.json"
                    );
                }
            }
        }
    }
}
