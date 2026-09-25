<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuoteRichText;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteRichTextApiTest extends TestCase
{
    use DatabaseTransactions;

    private const CAMPI = QuoteRichText::FIELDS;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => [UserRole::Admin]]));
    }

    private function quote(array $attrs = []): Quote
    {
        return Quote::factory()->create(array_merge(['additional_services' => [], 'discount' => 0], $attrs));
    }

    /** @test */
    public function show_espone_i_quattro_campi_in_italiano_o_null(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('payment_plan', 'it', '<ul><li>20% alla firma</li></ul>');
        $quote->save();

        $json = $this->getJson("/api/quotes/{$quote->id}")->assertOk()->json();

        $this->assertSame('<ul><li>20% alla firma</li></ul>', $json['payment_plan']);
        $this->assertNull($json['delivery_time']);
        $this->assertNull($json['additional_info']);
        $this->assertNull($json['billing_plan']);
    }

    /** @test */
    public function la_lettura_restituisce_il_testo_che_il_pdf_italiano_stampa(): void
    {
        // Il PDF usa il fallback di Spatie (fallbackAny): senza `it` stampa un'altra lingua.
        $quote = $this->quote();
        $quote->setTranslation('delivery_time', 'en', '<p>english only</p>');
        $quote->save();

        $this->assertSame('<p>english only</p>', $this->getJson("/api/quotes/{$quote->id}")->json('delivery_time'));
    }

    /** @test */
    public function svuotare_un_campo_con_testo_in_altra_lingua_restituisce_quello_che_resta_nel_pdf(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('billing_plan', 'it', '<p>it</p>');
        $quote->setTranslation('billing_plan', 'en', '<p>en</p>');
        $quote->save();

        $json = $this->patchJson("/api/quotes/{$quote->id}", ['billing_plan' => null])->assertOk()->json();

        $this->assertSame('<p>en</p>', $json['billing_plan'], 'la risposta deve dire che nel PDF resta il testo inglese');
        $quote->refresh();
        $this->assertArrayNotHasKey('it', $quote->getTranslations('billing_plan'));
        $this->assertSame('<p>en</p>', $quote->getTranslation('billing_plan', 'en', false), 'il testo inglese non va cancellato');
    }

    /** @test */
    public function store_salva_i_quattro_campi_esattamente_come_inviati(): void
    {
        $payload = ['title' => 'Nuovo', 'customer_id' => Customer::factory()->create()->id];
        foreach (self::CAMPI as $campo) {
            $payload[$campo] = "<p style=\"text-align: center;\"><strong>{$campo}</strong></p>";
        }

        $json = $this->postJson('/api/quotes', $payload)->assertStatus(201)->json();

        $quote = Quote::find($json['id']);
        foreach (self::CAMPI as $campo) {
            $this->assertSame($payload[$campo], $json[$campo]);
            $this->assertSame($payload[$campo], $quote->getTranslation($campo, 'it', false));
        }
    }

    /** @test */
    public function patch_parziale_non_tocca_gli_altri_campi(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('delivery_time', 'it', '<p>30 giorni</p>');
        $quote->setTranslation('payment_plan', 'de', '<p>legacy de</p>');
        $quote->save();

        $this->patchJson("/api/quotes/{$quote->id}", ['billing_plan' => '<p>50% + 50%</p>'])->assertOk();

        $quote->refresh();
        $this->assertSame('<p>30 giorni</p>', $quote->getTranslation('delivery_time', 'it', false));
        $this->assertSame('<p>legacy de</p>', $quote->getTranslation('payment_plan', 'de', false));
        $this->assertSame('<p>50% + 50%</p>', $quote->getTranslation('billing_plan', 'it', false));
    }

    /** @test */
    public function null_e_stringa_vuota_rimuovono_la_traduzione_it(): void
    {
        $quote = $this->quote();
        $quote->setTranslation('payment_plan', 'it', '<p>x</p>');
        $quote->setTranslation('delivery_time', 'it', '<p>y</p>');
        $quote->save();

        $json = $this->patchJson("/api/quotes/{$quote->id}", ['payment_plan' => null, 'delivery_time' => ''])->assertOk()->json();

        $this->assertNull($json['payment_plan']);
        $this->assertNull($json['delivery_time']);
        $quote->refresh();
        $this->assertArrayNotHasKey('it', $quote->getTranslations('payment_plan'));
        $this->assertArrayNotHasKey('it', $quote->getTranslations('delivery_time'));
    }

    /** @test */
    public function html_pericoloso_da_422_con_messaggio_comprensibile_e_non_salva(): void
    {
        $quote = $this->quote();

        $response = $this->patchJson("/api/quotes/{$quote->id}", ['payment_plan' => '<p onclick="x()">a</p><script>alert(1)</script>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_plan']);

        $messaggio = $response->json('errors.payment_plan.0');
        $this->assertStringContainsString('<script>', $messaggio);
        $this->assertStringContainsString('onclick', $messaggio);
        $this->assertArrayNotHasKey('it', $quote->fresh()->getTranslations('payment_plan'));
    }

    /** @test */
    public function oltre_50000_caratteri_da_422(): void
    {
        $quote = $this->quote();

        $this->patchJson("/api/quotes/{$quote->id}", ['additional_info' => '<p>' . str_repeat('a', 50001) . '</p>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['additional_info']);
    }

    /** @test */
    public function round_trip_di_contenuto_nova_reale_passa(): void
    {
        $html = '<p dir="ltr" style="text-align: justify;">Testo</p><div><span class="x">a</span></div>'
            . '<table><tbody><tr><td colspan="1" rowspan="1" colwidth="200"><p>c</p></td></tr></tbody></table>'
            . '<p><a href="https://webmapp.it" target="_blank" tt-mode="url">link</a></p>'
            . '<img src="/storage/tiptap/logo.png" tt-mode="file" alt="logo" title="Logo">';
        $quote = $this->quote();
        $quote->setTranslation('additional_info', 'it', $html);
        $quote->save();

        $letto = $this->getJson("/api/quotes/{$quote->id}")->json('additional_info');
        $this->patchJson("/api/quotes/{$quote->id}", ['additional_info' => $letto])->assertOk();

        $this->assertSame($html, $quote->fresh()->getTranslation('additional_info', 'it', false));
    }

    /** @test */
    public function prezzo_con_migliaia_in_additional_services_da_422(): void
    {
        $quote = $this->quote();

        $messaggio = $this->patchJson("/api/quotes/{$quote->id}", ['additional_services' => ['Setup' => '1.234,56']])
            ->assertStatus(422)
            ->json('errors.additional_services.0');

        $this->assertStringContainsString('"Setup"', $messaggio);
    }

    /** @test */
    public function spazi_e_a_capo_ai_bordi_vengono_mantenuti(): void
    {
        $quote = $this->quote();
        $html = "\n  <p>a</p>\n";

        $this->assertSame($html, $this->patchJson("/api/quotes/{$quote->id}", ['payment_plan' => $html])->assertOk()->json('payment_plan'));
    }

    /** @test */
    public function round_trip_di_immagine_tiptap_con_url_assoluto(): void
    {
        $html = '<p><img src="' . rtrim(config('app.url'), '/') . '/storage/tiptap/logo.png" tt-mode="file" alt="logo"></p>';
        $quote = $this->quote();
        $quote->setTranslation('additional_info', 'it', $html)->save();

        $letto = $this->getJson("/api/quotes/{$quote->id}")->json('additional_info');
        $this->patchJson("/api/quotes/{$quote->id}", ['additional_info' => $letto])->assertOk();

        $this->assertSame($html, $quote->fresh()->getTranslation('additional_info', 'it', false));
    }
}

