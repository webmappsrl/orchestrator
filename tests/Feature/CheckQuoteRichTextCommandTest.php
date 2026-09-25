<?php

namespace Tests\Feature;

use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CheckQuoteRichTextCommandTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function riporta_i_preventivi_che_verrebbero_rifiutati_senza_modificarli(): void
    {
        $ok = Quote::factory()->create(['additional_services' => []]);
        $ok->setTranslation('payment_plan', 'it', '<p>ok</p>')->save();

        $ko = Quote::factory()->create(['additional_services' => []]);
        $ko->setTranslation('delivery_time', 'de', '<p onclick="x()">a</p>');
        $ko->setTranslation('additional_services', 'it', ['Setup' => 'da definire']);
        $ko->save();
        $prima = $ko->fresh()->getAttributes();

        // Artisan::output(): con expectsOutputToContain una riga della tabella
        // soddisfa una sola aspettativa, e qui id, campo e motivo stanno sulla stessa riga.
        // Artisan::output() svuota il buffer a ogni lettura: leggerlo una volta sola.
        $exit = Artisan::call('quotes:check-rich-text');
        $output = Artisan::output();
        $riga = collect(explode("\n", $output))
            ->first(fn ($l) => str_contains($l, " {$ko->id} ") && str_contains($l, 'delivery_time [de]'));

        $this->assertSame(1, $exit);
        $this->assertNotNull($riga, 'Riga del preventivo non valido assente dal report');
        $this->assertStringContainsString('onclick', $riga);
        $this->assertStringContainsString('additional_services [it]', $output);
        $this->assertStringNotContainsString(" {$ok->id} ", $output);

        $this->assertEquals($prima, $ko->fresh()->getAttributes());
    }

    /** @test */
    public function applica_le_stesse_regole_dell_api_anche_alla_descrizione_vuota(): void
    {
        $ko = Quote::factory()->create(['additional_services' => []]);
        $ko->setTranslation('additional_services', 'it', ['  ' => 100])->save();

        $exit = Artisan::call('quotes:check-rich-text');
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('descrizione non vuota', $output);
    }
}

