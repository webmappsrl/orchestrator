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

            $changes[$field] = [
                'old' => self::readable($model, $field, $model->getRawOriginal($field)),
                'new' => self::readable($model, $field, $model->getAttributes()[$field] ?? null),
            ];
        }

        return $changes;
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
