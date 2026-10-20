<?php

namespace App\Services\Quotes;

use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Punto unico di scrittura dei link pubblici al PDF di un preventivo (oc:8733), usato da
 * POST /api/quotes/{quote}/pdf-link e dall'action Nova GenerateQuotePublicPdfLink.
 *
 * Il link si aggiunge in coda a quotes.pdf_links con un solo UPDATE: due generazioni
 * contemporanee non si sovrascrivono, e senza save() non partono gli hook `saving` della
 * Quote (il demote dei template sorelli) né cambia updated_at.
 */
class QuotePdfLinks
{
    public const DEFAULT_LANG = 'it';
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 90;

    /**
     * Chi può generare e vedere i link: l'URL apre il PDF senza login, quindi solo Admin e
     * Manager. Regola unica per l'endpoint, l'action Nova e i campi della scheda.
     */
    public static function canShare(?User $user): bool
    {
        return (bool) ($user?->hasRole(UserRole::Admin) || $user?->hasRole(UserRole::Manager));
    }

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function generate(Quote $quote, string $lang, int $days, User $user): array
    {
        $expiresAt = now()->addDays($days);

        $url = URL::temporarySignedRoute('quotes.pdf.public', $expiresAt, [
            'quote' => $quote->id,
            'lang'  => $lang,
        ]);

        $entry = [
            'url'        => $url,
            'lang'       => $lang,
            'expires_at' => $expiresAt->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'created_by' => $user->id,
        ];

        // L'array esterno fa sì che `||` aggiunga un elemento invece di unire due oggetti.
        DB::update(
            "update quotes set pdf_links = coalesce(pdf_links, '[]'::jsonb) || ?::jsonb where id = ?",
            [json_encode([$entry]), $quote->id]
        );

        return ['url' => $url, 'expires_at' => $expiresAt];
    }
}
