<?php

namespace App\Nova\Actions;

use App\Services\Quotes\QuotePdfLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Genera da Nova il link pubblico firmato al PDF di una Quote (oc:8733), con lo stesso
 * servizio di POST /api/quotes/{quote}/pdf-link: il link finisce in quotes.pdf_links.
 *
 * Solo Admin e Manager, come l'endpoint, e anche sulle trattative chiuse: senza canRun Nova
 * userebbe QuotePolicy::update(), che le rifiuta. Una Quote alla volta, solo dal dettaglio.
 */
class GenerateQuotePublicPdfLink extends Action
{
    public function __construct()
    {
        $this->canSee(fn (Request $request) => QuotePdfLinks::canShare($request->user()))
            ->canRun(fn (Request $request) => QuotePdfLinks::canShare($request->user()))
            ->onlyOnDetail();
    }

    public function name()
    {
        return __('Generate public link');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $quote = $models->sole();

        $link = app(QuotePdfLinks::class)->generate(
            $quote,
            $fields->get('lang'),
            (int) $fields->get('days'),
            auth()->user(),
        );

        return Action::message(__('Public link (expires on :date): :url', [
            'date' => $link['expires_at']->format('d/m/Y'),
            'url'  => $link['url'],
        ]));
    }

    public function fields(NovaRequest $request)
    {
        return [
            Heading::make(__('Links already sent stay valid until they expire: they cannot be revoked.')),
            Select::make(__('Language'), 'lang')
                ->options(['it' => 'Italiano', 'en' => 'English'])
                ->default(QuotePdfLinks::DEFAULT_LANG)
                ->rules('required', 'in:it,en'),
            Number::make(__('Validity (days)'), 'days')
                ->min(1)->max(QuotePdfLinks::MAX_DAYS)->step(1)
                ->default(QuotePdfLinks::DEFAULT_DAYS)
                ->rules('required', 'integer', 'min:1', 'max:' . QuotePdfLinks::MAX_DAYS),
        ];
    }
}
