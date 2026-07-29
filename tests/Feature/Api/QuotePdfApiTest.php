<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotePdfApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401_su_pdf(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->getJson("/api/quotes/{$quote->id}/pdf")->assertStatus(401);
    }

    /** @test */
    public function utente_autenticato_scarica_il_pdf(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = $this->get("/api/quotes/{$quote->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function pdf_link_genera_url_firmato_con_scadenza(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = $this->postJson("/api/quotes/{$quote->id}/pdf-link", [
            'lang' => 'it',
            'expires_in_days' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringContainsString('signature=', $response->json('url'));
    }

    /** @test */
    public function pdf_link_rifiuta_expires_in_days_oltre_90(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->postJson("/api/quotes/{$quote->id}/pdf-link", ['expires_in_days' => 91])
            ->assertStatus(422);
    }

    /** @test */
    public function link_pubblico_firmato_restituisce_il_pdf_senza_autenticazione(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'quotes.pdf.public',
            now()->addDays(1),
            ['quote' => $quote->id, 'lang' => 'it']
        );

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function link_pubblico_con_firma_non_valida_viene_rifiutato(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->get("/public/quotes/{$quote->id}/pdf?lang=it&expires=9999999999&signature=invalid")
            ->assertStatus(403);
    }

    /** @test */
    public function link_pubblico_con_quote_inesistente_e_firma_non_valida_restituisce_403_non_404(): void
    {
        $nonExistentId = 999999999;

        $this->get("/public/quotes/{$nonExistentId}/pdf?lang=it&expires=9999999999&signature=invalid")
            ->assertStatus(403);
    }

    /** @test */
    public function link_pubblico_firmato_non_modifica_updated_at_della_quote(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create([
            'customer_id' => $customer->id,
            'template' => true,
            'additional_services' => [],
        ]);

        $updatedAtBefore = $quote->fresh()->updated_at;

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'quotes.pdf.public',
            now()->addDays(1),
            ['quote' => $quote->id, 'lang' => 'it']
        );

        $this->get($url)->assertStatus(200);

        $updatedAtAfter = $quote->fresh()->updated_at;

        $this->assertTrue(
            $updatedAtBefore->equalTo($updatedAtAfter),
            'Public PDF link must not write to the database: updated_at changed.'
        );
    }

    /** @test */
    public function bearer_pdf_endpoint_non_cascata_template_false_su_quote_sorelle(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        // Non-empty (locale-keyed, with an empty-array locale mixed in)
        // `additional_services` is required so that
        // clearEmptyAdditionalServicesTranslations() doesn't early-return
        // before reaching save() — a plain `[]` short-circuits before any
        // write happens, which would make this test pass regardless of
        // the fix.
        $quoteA = Quote::factory()->create([
            'customer_id' => $customer->id,
            'template' => true,
            'additional_services' => ['it' => [], 'en' => ['Servizio' => '100']],
        ]);
        $quoteB = Quote::factory()->create([
            'customer_id' => $customer->id,
            'template' => true,
            'additional_services' => ['it' => [], 'en' => ['Servizio' => '100']],
        ]);

        // Quote::booted()'s `saving` hook auto-enforces "one template=true
        // per customer" on every Eloquent save, so creating quoteB above
        // already demoted quoteA to template=false in the DB. Force both
        // back to template=true via a raw query-builder update (this does
        // NOT instantiate models or fire Eloquent events, so the hook does
        // not run) to reproduce the exact precondition the endpoint must
        // not disturb: two template=true quotes for the same customer.
        DB::table('quotes')->whereIn('id', [$quoteA->id, $quoteB->id])->update(['template' => true]);
        $quoteA = $quoteA->fresh();
        $quoteB = $quoteB->fresh();
        $this->assertTrue($quoteA->template);
        $this->assertTrue($quoteB->template);

        $updatedAtBefore = $quoteA->fresh()->updated_at;

        $this->get("/api/quotes/{$quoteA->id}/pdf")->assertStatus(200);

        $this->assertTrue(
            $quoteB->fresh()->template,
            'Bearer PDF download must not persist and cascade template=false onto sibling quotes.'
        );

        $updatedAtAfter = $quoteA->fresh()->updated_at;
        $this->assertTrue(
            $updatedAtBefore->equalTo($updatedAtAfter),
            'Bearer PDF download must not write to the database: updated_at changed.'
        );
    }
}
