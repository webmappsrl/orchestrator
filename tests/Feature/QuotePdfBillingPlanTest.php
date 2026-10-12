<?php

namespace Tests\Feature;

use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class QuotePdfBillingPlanTest extends TestCase
{
    use DatabaseTransactions;

    private function html(Quote $quote): string
    {
        App::setLocale('it');
        return view('quote-pdf', ['quote' => $quote, 'config' => config('quote-pdf')])->render();
    }

    /** @test */
    public function il_piano_di_fatturazione_compare_dopo_il_piano_di_pagamento(): void
    {
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);
        $quote->setTranslation('payment_plan', 'it', '<p>PIANO-PAGAMENTO</p>');
        $quote->setTranslation('billing_plan', 'it', '<ul><li>FATTURA-UNO</li></ul>');
        $quote->save();

        $html = $this->html($quote->fresh());

        $this->assertStringContainsString('Piano di fatturazione', $html);
        $this->assertStringContainsString('<ul><li>FATTURA-UNO</li></ul>', $html);
        $this->assertGreaterThan(strpos($html, 'PIANO-PAGAMENTO'), strpos($html, 'FATTURA-UNO'));
    }

    /** @test */
    public function senza_piano_di_fatturazione_la_sezione_non_compare(): void
    {
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);

        $this->assertStringNotContainsString('Piano di fatturazione', $this->html($quote));
    }

    /**
     * Un prezzo non valido salvato prima di oc:8734 (da Nova non c'era controllo) viene stampato
     * così com'è, senza €, invece di mandare il PDF in errore.
     *
     * @test
     */
    public function un_prezzo_non_valido_gia_salvato_compare_cosi_com_e(): void
    {
        $quote = Quote::factory()->create([
            'additional_services' => ['it' => ['Formazione' => 'da definire', 'Hosting' => '1500']],
            'discount' => 0,
        ]);

        $html = $this->html($quote->fresh());

        $this->assertStringContainsString('da definire', $html);
        $this->assertDoesNotMatchRegularExpression('/da definire\s*€/', $html);
        $this->assertStringContainsString('1.500,00 €', $html);
    }
}
