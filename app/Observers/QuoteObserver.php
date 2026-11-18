<?php

namespace App\Observers;

use App\Models\Quote;
use App\Models\QuoteActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra fra le attività (oc:8724) creazione, modifica per campo e
 * cancellazione delle trattative.
 */
class QuoteObserver
{
    /**
     * Campi scritti dal sistema, non da una persona: non diventano attività.
     * `priority` è l'ordine delle Card nel Kanban Sales: spostando una Card di
     * colonna il KanbanController la ricalcola insieme allo stato.
     */
    public const IGNORED_FIELDS = ['pdf_links', 'template', 'priority', 'created_at', 'updated_at'];

    public function created(Quote $quote): void
    {
        QuoteActivity::record(QuoteActivity::EVENT_QUOTE_CREATED, $quote);
    }

    public function updated(Quote $quote): void
    {
        $changes = self::fieldChanges($quote, self::IGNORED_FIELDS);

        if ($changes === []) {
            return;
        }

        QuoteActivity::record(QuoteActivity::EVENT_QUOTE_UPDATED, $quote, ['changes' => $changes]);
    }

    public function deleted(Quote $quote): void
    {
        QuoteActivity::record(QuoteActivity::EVENT_QUOTE_DELETED, $quote);
    }

    /**
     * Campi cambiati dall'ultimo salvataggio, con valore prima e dopo. I campi
     * JSON (tradotti o con cast array) si registrano decodificati, così restano
     * leggibili invece di essere una stringa JSON.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function fieldChanges(Model $model, array $ignored): array
    {
        $changes = [];

        foreach (array_keys($model->getChanges()) as $field) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $old = self::unixLineEndings(self::readable($model, $field, $model->getRawOriginal($field)));
            $new = self::unixLineEndings(self::readable($model, $field, $model->getAttributes()[$field] ?? null));

            if (in_array($field, $model->translatable ?? [], true)) {
                $old = self::filledTranslations($old);
                $new = self::filledTranslations($new);

                if (self::sameTranslations($old, $new)) {
                    continue;
                }
            } elseif ($old === $new) {
                // Cambiati solo gli a capo: non è una modifica.
                continue;
            }

            $changes[$field] = ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    /**
     * Il browser invia gli a capo dei campi di testo come `\r\n`, l'API li riceve
     * di solito come `\n`: il primo salvataggio da Nova di un testo scritto via
     * API cambia la stringa anche se il testo è lo stesso. Gli a capo si portano
     * a `\n`, anche dentro le lingue dei campi tradotti, sia per il confronto
     * sia per ciò che si registra.
     */
    private static function unixLineEndings(mixed $value): mixed
    {
        if (is_string($value)) {
            return str_replace("\r\n", "\n", $value);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => self::unixLineEndings($item), $value);
        }

        return $value;
    }

    /**
     * NovaTabTranslatable salva tutte le lingue, quelle vuote come `null` (campi
     * di testo) o `[]` (KeyValue, come `additional_services`): il primo
     * salvataggio da Nova cambia la stringa JSON anche se nessun valore è
     * cambiato. Di un campo tradotto si tengono solo le lingue con un valore,
     * sia per il confronto sia per ciò che si registra.
     */
    private static function filledTranslations(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_filter($value, fn (mixed $text): bool => ! in_array($text, [null, '', []], true));
    }

    /**
     * Confronto fra due campi tradotti già ripuliti delle lingue vuote: l'ordine
     * delle lingue nel JSON non conta, e un campo mai valorizzato (`null`)
     * equivale a un oggetto senza lingue.
     */
    private static function sameTranslations(mixed $old, mixed $new): bool
    {
        if (is_array($old)) {
            ksort($old);
        }
        if (is_array($new)) {
            ksort($new);
        }

        return ($old ?: []) === ($new ?: []);
    }

    private static function readable(Model $model, string $field, mixed $value): mixed
    {
        $isJson = in_array($field, $model->translatable ?? [], true)
            || in_array($model->getCasts()[$field] ?? null, ['array', 'json'], true);

        if ($isJson && is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }
}
