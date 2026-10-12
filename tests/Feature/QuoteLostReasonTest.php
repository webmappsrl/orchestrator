<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Il motivo della chiusura persa esiste solo sulle trattative perse (oc:8732):
 * l'hook saving di Quote lo azzera per ogni altro stato, da qualunque canale arrivi il salvataggio.
 */
class QuoteLostReasonTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(array $attributes = []): Quote
    {
        return Quote::create(array_merge([
            'title' => 'Portale Parco XY',
            'customer_id' => Customer::factory()->create()->id,
        ], $attributes));
    }

    public function test_lost_quote_keeps_its_reason(): void
    {
        $quote = $this->makeQuote([
            'status' => QuoteStatus::Closed_Lost->value,
            'lost_reason' => 'Il cliente ha scelto un fornitore più economico',
        ]);

        $this->assertSame('Il cliente ha scelto un fornitore più economico', $quote->fresh()->lost_reason);
    }

    public function test_open_quote_never_stores_a_reason(): void
    {
        $quote = $this->makeQuote([
            'status' => QuoteStatus::Presented->value,
            'lost_reason' => 'Testo rimasto nel campo nascosto',
        ]);

        $this->assertNull($quote->fresh()->lost_reason);
    }

    public function test_reopening_a_lost_quote_clears_the_reason(): void
    {
        $quote = $this->makeQuote([
            'status' => QuoteStatus::Closed_Lost->value,
            'lost_reason' => 'Prezzo troppo alto',
        ]);

        // Stesso percorso del Kanban: cambio di stato e save(), senza policy.
        $quote->status = QuoteStatus::Presented->value;
        $quote->save();

        $this->assertNull($quote->fresh()->lost_reason);
    }
}
