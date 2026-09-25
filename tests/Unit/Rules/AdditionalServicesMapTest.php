<?php

namespace Tests\Unit\Rules;

use App\Rules\AdditionalServicesMap;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AdditionalServicesMapTest extends TestCase
{
    private function errore(mixed $value): ?string
    {
        App::setLocale('it');
        $validator = Validator::make(['additional_services' => $value], ['additional_services' => ['nullable', 'array', new AdditionalServicesMap()]]);
        return $validator->errors()->first('additional_services') ?: null;
    }

    /** @test */
    public function prezzi_validi_passano(): void
    {
        $this->assertNull($this->errore(['Setup' => 1500, 'Hosting' => 99.5, 'Formazione' => '1234,56', 'Extra' => '-10.00', 'Base' => '1234']));
        $this->assertNull($this->errore([]));
        $this->assertNull($this->errore(null));
    }

    /** @test */
    public function separatore_delle_migliaia_e_rifiutato_con_esempi(): void
    {
        $messaggio = $this->errore(['Setup iniziale' => '1.234,56']);

        $this->assertStringContainsString('"Setup iniziale"', $messaggio);
        $this->assertStringContainsString('1.234,56', $messaggio);
        $this->assertStringContainsString('Esempi validi', $messaggio);
    }

    /** @test */
    public function testo_descrittivo_al_posto_del_prezzo_e_rifiutato(): void
    {
        $this->assertStringContainsString('"Consulenza"', $this->errore(['Consulenza' => 'da definire']));
    }

    /** @test */
    public function una_lista_e_rifiutata(): void
    {
        $this->assertStringContainsString('non una lista', $this->errore([150, 200]));
    }

    /** @test */
    public function is_valid_price_ricalca_template_e_totale(): void
    {
        foreach ([0, 10, 10.5, '10', '10.5', '10,50', '-3'] as $ok) {
            $this->assertTrue(AdditionalServicesMap::isValidPrice($ok), var_export($ok, true));
        }
        foreach (['1.234,56', '1,234.56', '10.555', 'abc', '', ' 10', true, null, [1]] as $ko) {
            $this->assertFalse(AdditionalServicesMap::isValidPrice($ko), var_export($ko, true));
        }
    }

    /** @test */
    public function un_numero_con_piu_di_due_decimali_e_rifiutato(): void
    {
        $this->assertFalse(AdditionalServicesMap::isValidPrice(1234.567));
        $this->assertTrue(AdditionalServicesMap::isValidPrice(99.5));
        $this->assertTrue(AdditionalServicesMap::isValidPrice(0.1 + 0.2 - 0.3 + 12.34));
    }

    /** @test */
    public function il_messaggio_per_la_lista_non_ripete_il_campo_e_spiega_le_chiavi_numeriche(): void
    {
        $messaggio = $this->errore([150, 200]);

        $this->assertSame(1, substr_count($messaggio, 'additional_services'));
        $this->assertStringContainsString('Voce 1', $messaggio);
    }

    /** @test */
    public function i_segnaposto_nel_nome_del_servizio_non_vengono_sostituiti(): void
    {
        $messaggio = $this->errore(['Setup :attribute :input' => 'x']);

        $this->assertStringNotContainsString('Setup additional services', $messaggio);
        $this->assertStringContainsString("Setup :\u{2060}attribute :\u{2060}input", $messaggio);
    }
}

