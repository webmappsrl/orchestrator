<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Services\QuotePdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuotePdfServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function file_name_sanitizza_caratteri_non_alfanumerici_dal_nome_cliente(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Acme "Corp" / Srl <script>&']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $fileName = (new QuotePdfService())->fileName($quote);

        $this->assertStringEndsWith('.pdf', $fileName);
        $this->assertStringNotContainsString('/', $fileName);
        $this->assertStringNotContainsString('"', $fileName);
        $this->assertStringNotContainsString('<', $fileName);
        $this->assertStringNotContainsString('&', $fileName);
    }

    /** @test */
    public function file_name_tronca_nomi_molto_lunghi(): void
    {
        $customer = Customer::factory()->create(['full_name' => str_repeat('A', 200)]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $fileName = (new QuotePdfService())->fileName($quote);

        $this->assertLessThanOrEqual(110, strlen($fileName));
    }

    /** @test */
    public function stream_restituisce_una_response_pdf(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = (new QuotePdfService())->stream($quote, 'it');

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function clear_empty_translations_normalizza_in_memoria_anche_senza_persistere(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'additional_services' => [
                'it' => [],
                'en' => ['Servizio' => '100'],
            ],
        ]);

        // Sanity check: the empty-array locale key exists before cleanup,
        // which is exactly what breaks Spatie's fallback.
        $this->assertArrayHasKey('it', $quote->getTranslations('additional_services'));

        $quote->clearEmptyAdditionalServicesTranslations(false);

        $translationsAfter = $quote->getTranslations('additional_services');
        $this->assertArrayNotHasKey('it', $translationsAfter);
        $this->assertArrayHasKey('en', $translationsAfter);

        // persist: false must not touch the database.
        $fromDb = $quote->fresh();
        $this->assertArrayHasKey('it', $fromDb->getTranslations('additional_services'));
        $this->assertEquals([], $fromDb->getTranslation('additional_services', 'it'));
    }

    /** @test */
    public function stream_con_persist_false_normalizza_in_memoria_senza_scrivere_su_db(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'additional_services' => [
                'it' => [],
                'en' => ['Servizio' => '100'],
            ],
        ]);

        $response = (new QuotePdfService())->stream($quote, 'it', persist: false);

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertArrayNotHasKey('it', $quote->getTranslations('additional_services'));

        $fromDb = $quote->fresh();
        $this->assertArrayHasKey('it', $fromDb->getTranslations('additional_services'));
    }

    /**
     * `additional_services` is a nullable json column and the factory always
     * populates an array, so the null state has to be forced on the column
     * directly. See oc:8413.
     */
    private function forceAdditionalServices(Quote $quote, $value): Quote
    {
        DB::table('quotes')->where('id', $quote->id)->update(['additional_services' => $value]);

        return $quote->refresh();
    }

    /** @test */
    public function stream_non_esplode_con_additional_services_null(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $this->forceAdditionalServices($quote, null);

        $response = (new QuotePdfService())->stream($quote, 'it');

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function stream_non_esplode_con_additional_services_stringa_json(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $this->forceAdditionalServices($quote, '{"Servizio":"100"}');

        $response = (new QuotePdfService())->stream($quote, 'it');

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * Covers the path that actually produced the 500 in production:
     * QuoteController@show -> clearEmptyAdditionalServicesTranslations(true)
     * -> QuotePdfService::stream().
     *
     * @test
     */
    public function rotta_web_quote_pdf_risponde_200_con_additional_services_null(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);
        $this->forceAdditionalServices($quote, null);

        $this->get("/quote/{$quote->id}")->assertOk();
    }
}
