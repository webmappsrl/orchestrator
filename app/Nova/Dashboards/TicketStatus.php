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
        $statuses = [];
        foreach (StoryStatus::cases() as $statusCase) {
            $statuses[] = [
                'value' => $statusCase->value,
                'label' => __(ucfirst($statusCase->value)),
                'color' => $statusCase->color(),
                'icon' => $statusCase->icon(),
                'description' => match($statusCase) {
                    StoryStatus::New => __('Il ticket è stato appena creato e non è ancora stato assegnato a nessun sviluppatore.'),
                    StoryStatus::Backlog => __('Il ticket è stato messo in coda per essere lavorato in futuro. Non è ancora in lavorazione attiva.'),
                    StoryStatus::Assigned => __('Il ticket è stato assegnato a uno sviluppatore ma non è ancora iniziato il lavoro.'),
                    StoryStatus::Todo => __('Il ticket è pronto per essere lavorato dallo sviluppatore assegnato.'),
                    StoryStatus::Progress => __('Il ticket è attualmente in lavorazione da parte dello sviluppatore assegnato.'),
                    StoryStatus::Test => __('Il ticket è stato completato dallo sviluppatore e ora è in fase di verifica da parte di un tester.'),
                    StoryStatus::Tested => __('Il ticket è stato testato positivamente e può essere rilasciato o è pronto per la produzione.'),
                    StoryStatus::Released => __('Il ticket è stato rilasciato in produzione e completato con successo.'),
                    StoryStatus::Done => __('Il ticket è completamente terminato e chiuso.'),
                    StoryStatus::Problem => __('Lo sviluppatore ha incontrato un problema tecnico che non riesce a risolvere autonomamente e richiede supporto.'),
                    StoryStatus::Waiting => __('Il ticket è in pausa in attesa di informazioni, approvazioni o azioni da parte di altre persone.'),
                    StoryStatus::Rejected => __('Il ticket è stato rifiutato e non verrà implementato.'),
                },
            ];
        }

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
        return __('Stati ticket');
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
