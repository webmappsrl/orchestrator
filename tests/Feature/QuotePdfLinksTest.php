<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Services\Quotes\QuotePdfLinks;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Punto unico di scrittura dei link pubblici al PDF (oc:8733): genera l'URL firmato e lo
 * aggiunge in coda a quotes.pdf_links, senza save() sulla Quote.
 */
class QuotePdfLinksTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(array $overrides = []): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'additional_services' => [],
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['roles' => [UserRole::Admin]]);
    }

    /** @test */
    public function generate_restituisce_un_url_firmato_e_salva_il_link(): void
    {
        $quote = $this->makeQuote();
        $user = $this->admin();

        $result = app(QuotePdfLinks::class)->generate($quote, 'en', 10, $user);

        $this->assertTrue(URL::hasValidSignature(Request::create($result['url'])));
        $this->assertStringContainsString("/public/quotes/{$quote->id}/pdf", $result['url']);
        $this->assertTrue($result['expires_at']->between(now()->addDays(10)->subMinute(), now()->addDays(10)->addMinute()));

        $links = $quote->fresh()->pdf_links;
        $this->assertCount(1, $links);
        $this->assertSame($result['url'], $links[0]['url']);
        $this->assertSame('en', $links[0]['lang']);
        $this->assertSame($result['expires_at']->toIso8601String(), $links[0]['expires_at']);
        $this->assertSame($user->id, $links[0]['created_by']);
        $this->assertNotEmpty($links[0]['created_at']);
    }

    /** @test */
    public function una_seconda_generazione_aggiunge_in_coda_senza_toccare_la_prima(): void
    {
        $quote = $this->makeQuote();
        $user = $this->admin();
        $service = app(QuotePdfLinks::class);

        $first = $service->generate($quote, 'it', 30, $user);
        $firstSaved = $quote->fresh()->pdf_links[0];
        $second = $service->generate($quote, 'en', 60, $user);

        $links = $quote->fresh()->pdf_links;
        $this->assertCount(2, $links);
        $this->assertSame($firstSaved, $links[0]);
        $this->assertSame($first['url'], $links[0]['url']);
        $this->assertSame($second['url'], $links[1]['url']);
    }

    /** @test */
    public function generate_non_modifica_updated_at_della_quote(): void
    {
        $quote = $this->makeQuote();
        $updatedAtBefore = $quote->fresh()->updated_at;

        $this->travel(5)->minutes();
        app(QuotePdfLinks::class)->generate($quote, 'it', 30, $this->admin());

        $this->assertTrue($updatedAtBefore->equalTo($quote->fresh()->updated_at));
    }

    /** @test */
    public function generate_su_un_template_non_tocca_le_quote_sorelle(): void
    {
        $customer = Customer::factory()->create();
        $quoteA = $this->makeQuote(['customer_id' => $customer->id, 'template' => true]);
        $quoteB = $this->makeQuote(['customer_id' => $customer->id, 'template' => true]);
        // Il saving hook ha già tolto template a quoteA: lo si rimette con un update diretto,
        // come in QuotePdfApiTest, per avere due template dello stesso cliente.
        DB::table('quotes')->whereIn('id', [$quoteA->id, $quoteB->id])->update(['template' => true]);

        app(QuotePdfLinks::class)->generate($quoteA->fresh(), 'it', 30, $this->admin());

        $this->assertTrue($quoteA->fresh()->template);
        $this->assertTrue($quoteB->fresh()->template);
    }
}
