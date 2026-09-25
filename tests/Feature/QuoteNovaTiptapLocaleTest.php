<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Nova\Quote as QuoteResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

/**
 * oc:8631: i Tiptap del preventivo stanno dentro NovaTabTranslatable; il
 * testo scritto nella tab di una lingua deve finire sotto quella lingua.
 */
class QuoteNovaTiptapLocaleTest extends TestCase
{
    use DatabaseTransactions;

    private function flatten(array $fields): array
    {
        $flat = [];
        foreach ($fields as $field) {
            // TabsGroup (Nova) e NovaTabTranslatable tengono i figli in $data.
            if (property_exists($field, 'data') && is_array($field->data)) {
                $flat = array_merge($flat, $this->flatten($field->data));
            } else {
                $flat[] = $field;
            }
        }
        return $flat;
    }

    /** @test */
    public function il_tiptap_della_tab_it_salva_sotto_la_chiave_it(): void
    {
        $quote = Quote::create(['title' => 'T', 'customer_id' => Customer::factory()->create()->id]);

        foreach (['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'] as $campo) {
            $payload = [];
            foreach (config('tab-translatable.locales') as $locale) {
                $payload["translations_{$campo}_{$locale}"] = $locale === 'it' ? "<p>{$campo} it</p>" : '';
            }
            $request = NovaRequest::create('/nova-api/quotes/' . $quote->id, 'PUT', $payload);

            $trovati = 0;
            foreach ($this->flatten((new QuoteResource($quote))->fields($request)) as $field) {
                if (str_starts_with($field->attribute ?? '', "translations_{$campo}_")) {
                    $field->fill($request, $quote);
                    $trovati++;
                }
            }

            $this->assertSame(count(config('tab-translatable.locales')), $trovati, "{$campo}: campi tradotti non trovati");
            $this->assertSame("<p>{$campo} it</p>", $quote->getTranslation($campo, 'it', false), "{$campo}: testo della tab it non salvato sotto it");
        }
    }
}
