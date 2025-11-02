<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class TicketStatus extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        // Preparo i dati degli stati con descrizioni
        $statuses = [
            [
                'value' => 'new',
                'label' => __('Nuovo'),
                'color' => '#3b82f6',
                'description' => __('Il ticket è stato appena creato e non è ancora stato assegnato a nessun sviluppatore.'),
            ],
            [
                'value' => 'backlog',
                'label' => __('Backlog'),
                'color' => '#64748b',
                'description' => __('Il ticket è stato messo in coda per essere lavorato in futuro. Non è ancora in lavorazione attiva.'),
            ],
            [
                'value' => 'assigned',
                'label' => __('Assegnato'),
                'color' => '#ea580c',
                'description' => __('Il ticket è stato assegnato a uno sviluppatore ma non è ancora iniziato il lavoro.'),
            ],
            [
                'value' => 'todo',
                'label' => __('Da fare'),
                'color' => '#f97316',
                'description' => __('Il ticket è pronto per essere lavorato dallo sviluppatore assegnato.'),
            ],
            [
                'value' => 'progress',
                'label' => __('In corso'),
                'color' => '#fb923c',
                'description' => __('Il ticket è attualmente in lavorazione da parte dello sviluppatore assegnato.'),
            ],
            [
                'value' => 'testing',
                'label' => __('In test'),
                'color' => '#fdba74',
                'description' => __('Il ticket è stato completato dallo sviluppatore e ora è in fase di verifica da parte di un tester.'),
            ],
            [
                'value' => 'tested',
                'label' => __('Testato'),
                'color' => '#86efac',
                'description' => __('Il ticket è stato testato positivamente e può essere rilasciato o è pronto per la produzione.'),
            ],
            [
                'value' => 'released',
                'label' => __('Rilasciato'),
                'color' => '#16a34a',
                'description' => __('Il ticket è stato rilasciato in produzione e completato con successo.'),
            ],
            [
                'value' => 'done',
                'label' => __('Completato'),
                'color' => '#4ade80',
                'description' => __('Il ticket è completamente terminato e chiuso.'),
            ],
            [
                'value' => 'problem',
                'label' => __('Problema'),
                'color' => '#dc2626',
                'description' => __('Lo sviluppatore ha incontrato un problema tecnico che non riesce a risolvere autonomamente e richiede supporto.'),
            ],
            [
                'value' => 'waiting',
                'label' => __('In attesa'),
                'color' => '#eab308',
                'description' => __('Il ticket è in pausa in attesa di informazioni, approvazioni o azioni da parte di altre persone.'),
            ],
            [
                'value' => 'rejected',
                'label' => __('Respinto'),
                'color' => '#dc2626',
                'description' => __('Il ticket è stato rifiutato e non verrà implementato.'),
            ],
        ];

        return [
            (new HtmlCard)
                ->width('full')
                ->view('ticket-status-documentation', [
                    'statuses' => $statuses,
                ])
                ->center(true),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Documentazione Stati Ticket');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'ticket-status';
    }
}
