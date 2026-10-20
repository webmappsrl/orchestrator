<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use App\Nova\Actions\DuplicateQuote;
use App\Nova\Actions\GenerateQuotePublicPdfLink;
use App\Nova\ArchivedQuotes;
use App\Nova\Quote as QuoteResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Tests\TestCase;

/**
 * Action Nova «Genera link pubblico» e campi «Link attivi»/«Storico» della tab Main (oc:8733).
 */
class GenerateQuotePublicPdfLinkActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Nova::resourcesIn(app_path('Nova'));
    }

    private function makeQuote(QuoteStatus $status = QuoteStatus::New): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::factory()->create([
            'customer_id' => $customer->id,
            'additional_services' => [],
            'status' => $status->value,
        ]);
    }

    private function loginAs(UserRole $role): User
    {
        $user = User::factory()->create(['roles' => [$role]]);
        $this->actingAs($user);

        return $user;
    }

    private function runAction(Quote $quote, string $lang = 'it', int $days = 30)
    {
        $fields = new ActionFields(collect(['lang' => $lang, 'days' => $days]), collect());

        return (new GenerateQuotePublicPdfLink)->handle($fields, collect([$quote]));
    }

    /** @test */
    public function l_action_salva_il_link_e_mostra_url_e_scadenza(): void
    {
        $user = $this->loginAs(UserRole::Admin);
        $quote = $this->makeQuote();

        $response = $this->runAction($quote, 'en', 15);

        $links = $quote->fresh()->pdf_links;
        $this->assertCount(1, $links);
        $this->assertSame('en', $links[0]['lang']);
        $this->assertSame($user->id, $links[0]['created_by']);
        $this->assertStringContainsString($links[0]['url'], $response['message']);
        $this->assertStringContainsString(now()->addDays(15)->format('d/m/Y'), $response['message']);
    }

    /** @test */
    public function l_action_funziona_anche_su_una_trattativa_chiusa(): void
    {
        $this->loginAs(UserRole::Manager);
        $quote = $this->makeQuote(QuoteStatus::Closed_Won);

        $this->runAction($quote);

        $this->assertCount(1, $quote->fresh()->pdf_links);
    }

    /** @test */
    public function solo_admin_e_manager_vedono_ed_eseguono_l_action(): void
    {
        $quote = $this->makeQuote(QuoteStatus::Closed_Lost);

        foreach ([[UserRole::Admin, true], [UserRole::Manager, true], [UserRole::Developer, false]] as [$role, $allowed]) {
            $request = NovaRequest::create('/nova-api/quotes/action', 'POST');
            $request->setUserResolver(fn () => User::factory()->create(['roles' => [$role]]));
            $action = new GenerateQuotePublicPdfLink;

            $this->assertSame($allowed, $action->authorizedToSee($request), "canSee per {$role->value}");
            $this->assertSame($allowed, $action->authorizedToRun($request, $quote), "canRun per {$role->value}");
        }
    }

    /** @test */
    public function l_action_e_registrata_su_quote_e_su_archived_quotes(): void
    {
        $request = NovaRequest::create('/nova-api/quotes', 'GET');

        $onQuote = collect((new QuoteResource(new Quote))->actions($request))->map(fn ($a) => $a::class);
        $onArchived = collect((new ArchivedQuotes(new Quote))->actions($request))->map(fn ($a) => $a::class);

        $this->assertContains(GenerateQuotePublicPdfLink::class, $onQuote);
        $this->assertContains(DuplicateQuote::class, $onQuote);
        $this->assertContains(GenerateQuotePublicPdfLink::class, $onArchived);
    }

    /** @test */
    public function un_link_scaduto_non_e_fra_gli_attivi_ma_resta_nello_storico(): void
    {
        $author = $this->loginAs(UserRole::Admin);
        $quote = $this->makeQuote();
        $this->runAction($quote, 'it', 1);
        $this->runAction($quote, 'en', 30);
        $this->travel(2)->days();

        $resource = new QuoteResource($quote->fresh());
        $active = $resource->pdfLinksHtml(activeOnly: true);
        $history = $resource->pdfLinksHtml(activeOnly: false);
        [$expired, $valid] = $quote->fresh()->pdf_links;

        $this->assertStringNotContainsString(e($expired['url']), $active);
        $this->assertStringContainsString(e($valid['url']), $active);
        // L'indirizzo è visibile come testo del link, non solo nell'href.
        $this->assertStringContainsString('>' . e($valid['url']) . '</a>', $active);
        $this->assertStringContainsString('>' . e($expired['url']) . '</a>', $history);
        $this->assertStringContainsString(e($expired['url']), $history);
        $this->assertStringContainsString(e($valid['url']), $history);
        $this->assertStringContainsString(e($author->name), $history);
        $this->assertStringContainsString(e(__('The link always opens the current version of the quote.')), $history);
        $this->assertStringNotContainsString(e(__('The link always opens the current version of the quote.')), $active);
    }

    /** @test */
    public function senza_link_i_campi_mostrano_un_trattino(): void
    {
        $resource = new QuoteResource($this->makeQuote());

        $this->assertSame('—', $resource->pdfLinksHtml(activeOnly: true));
        $this->assertSame('—', $resource->pdfLinksHtml(activeOnly: false));
    }

    /** @test */
    public function solo_admin_e_manager_vedono_i_campi_dei_link(): void
    {
        $quote = $this->makeQuote();
        $labels = [__('Active links'), __('Links history')];

        foreach ([[UserRole::Admin, true], [UserRole::Manager, true], [UserRole::Developer, false]] as [$role, $allowed]) {
            $this->loginAs($role);
            $request = NovaRequest::create("/nova-api/quotes/{$quote->id}", 'GET');
            $request->setUserResolver(fn () => auth()->user());

            // I campi risolti davvero per il dettaglio, non solo la regola: se un campo perde
            // il suo ->canSee() questo test lo vede.
            $names = (new QuoteResource($quote))->detailFields($request)->pluck('name')->all();

            foreach ($labels as $label) {
                $this->assertSame($allowed, in_array($label, $names, true), "{$label} per {$role->value}");
            }
        }
    }

    /** @test */
    public function la_copia_di_una_trattativa_non_eredita_i_link(): void
    {
        $this->loginAs(UserRole::Admin);
        $quote = $this->makeQuote();
        $this->runAction($quote);
        $before = Quote::max('id');

        (new DuplicateQuote)->handle(new ActionFields(collect(), collect()), collect([$quote->fresh()]));

        $copy = Quote::where('id', '>', $before)->sole();
        $this->assertNull($copy->pdf_links);
        $this->assertCount(1, $quote->fresh()->pdf_links);
    }
}
