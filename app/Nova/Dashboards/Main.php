<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Nova\Metrics\TotApps;
use App\Nova\Metrics\TotCustomers;
use App\Nova\Metrics\TotEpics;
use App\Nova\Metrics\TotLayers;
use App\Nova\Metrics\TotMilestones;
use App\Nova\Metrics\TotProjects;
use App\Nova\Metrics\TotStories;
use App\Services\StoryFetcher;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    /*---Helper---*/
    /**
     * Crea una card per visualizzare le storie filtrate per status
     *
     * PROBLEMA RISOLTO - LEZIONE DI FORMAZIONE:
     *
     * ❌ CODICE ORIGINALE (ERRATO):
     * return (new HtmlCard)->view('story-viewer')->withMeta(['stories' => $stories]);
     *
     * ✅ CODICE CORRETTO:
     * return (new HtmlCard)->view('story-viewer', ['stories' => $stories]);
     *
     * SPIEGAZIONE DEL PROBLEMA:
     * - withMeta() serve per passare dati ai componenti Nova/JavaScript
     * - NON rende i dati disponibili come variabili Blade ($stories, $statusLabel)
     * - Il secondo parametro di view() passa i dati direttamente alla vista Blade
     *
     * RISULTATO:
     * - Prima: $stories era undefined → "Nessuna storia disponibile"
     * - Dopo: $stories è accessibile → storie visualizzate correttamente
     */
    protected function storyCard(string $status, string $label, $user, $width = '1/3')
    {
        // Recupera le storie filtrate per status e utente
        $stories = StoryFetcher::byStatusAndUser($status, $user);

        return (new HtmlCard)
            ->width($width)
            // IMPORTANTE: Passare i dati come secondo parametro, NON con withMeta()
            ->view('story-viewer', [
                'stories' => $stories,        // Array di storie da visualizzare
                'statusLabel' => $label,      // Etichetta per il titolo della card
            ])
            ->center(true);
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $user = auth()->user();

        return [
            $this->storyCard('todo', __('To Do'), $user, 'full'),
            $this->storyCard('progress', __('Progress'), $user, 'full'),
            $this->storyCard('tobetested', __('Test'), $user, '1/2'),
            $this->storyCard('tested', __('Tested'), $user, '1/2'),

            (new TotCustomers)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            }),

            (new TotProjects)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            }),

            (new TotMilestones)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            }),

            (new TotEpics)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            }),

            (new TotStories)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            }),

            (new TotApps)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Editor);
            }),

            (new TotLayers)->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Editor);
            }),

            (new HtmlCard)->width('1/3')->view('favorite')->canSee(function ($request) {
                $user = $request->user();

                return $user->hasRole(UserRole::Developer);
            })->center(true),

        ];
    }
}
