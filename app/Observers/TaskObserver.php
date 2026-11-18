<?php

namespace App\Observers;

use App\Models\QuoteActivity;
use App\Models\Task;
use Throwable;

/**
 * Registra fra le attività delle trattative (oc:8724) creazione, chiusura,
 * riapertura, note aggiunte, modifiche e cancellazione dei task.
 */
class TaskObserver
{
    /**
     * `completed_at` segue `status` (Task::booted), `creator_id` si fissa alla
     * creazione: non sono modifiche fatte da una persona.
     */
    public const IGNORED_FIELDS = ['completed_at', 'creator_id', 'created_at', 'updated_at'];

    /** Intestazione scritta da `Task::appendNote`, dopo strip_tags. */
    private const APPEND_NOTE_HEADER = '/^.*? ha aggiunto una nota il: \d{2}-\d{2}-\d{4} \d{2}:\d{2}\s*/u';

    public function created(Task $task): void
    {
        $this->record(QuoteActivity::EVENT_TASK_CREATED, $task);
    }

    public function updated(Task $task): void
    {
        try {
            $changes = QuoteObserver::fieldChanges($task, self::IGNORED_FIELDS);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if (isset($changes['status'])) {
            $event = $changes['status']['new'] === Task::STATUS_COMPLETED
                ? QuoteActivity::EVENT_TASK_COMPLETED
                : QuoteActivity::EVENT_TASK_REOPENED;
            $this->record($event, $task);
            unset($changes['status']);
        }

        if (isset($changes['notes'])) {
            $added = self::addedNote($changes['notes']['old'], $changes['notes']['new']);

            if ($added !== null) {
                $this->record(QuoteActivity::EVENT_TASK_NOTE_ADDED, $task, ['note' => $added]);
                unset($changes['notes']);
            }
        }

        if ($changes !== []) {
            $this->record(QuoteActivity::EVENT_TASK_UPDATED, $task, $changes);
        }
    }

    public function deleted(Task $task): void
    {
        $this->record(QuoteActivity::EVENT_TASK_DELETED, $task);
    }

    /**
     * `Task::appendNote` mette la nota nuova in cima al testo esistente: se il
     * testo nuovo finisce con quello vecchio, la parte in più è la nota
     * aggiunta, restituita senza HTML e senza l'intestazione «<nome> ha
     * aggiunto una nota il: <data>» che `appendNote` le mette davanti (autore e
     * ora sono già in `user_name` e `created_at`). Altrimenti il campo è stato
     * riscritto a mano e si restituisce null.
     */
    public static function addedNote(?string $old, ?string $new): ?string
    {
        $old ??= '';
        $new ??= '';

        if ($new === $old || ! str_ends_with($new, $old)) {
            return null;
        }

        $added = trim(preg_replace('/\s+/', ' ', strip_tags(substr($new, 0, strlen($new) - strlen($old)))));
        $added = trim(preg_replace(self::APPEND_NOTE_HEADER, '', $added, 1));

        return $added === '' ? null : $added;
    }

    private function record(string $event, Task $task, ?array $changes = null): void
    {
        try {
            $quote = $task->quote;

            QuoteActivity::record($event, $quote, [
                'task_id' => $task->exists ? $task->getKey() : null,
                'task_title' => $task->title,
                'changes' => $changes,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
