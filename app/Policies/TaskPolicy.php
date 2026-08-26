<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Stesso perimetro ruoli di QuotePolicy: solo Admin, Manager, Developer
     * possono accedere alle API Task, indipendentemente dall'ability.
     */
    public function before(User $user)
    {
        if (!($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer))) {
            return false;
        }
    }

    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Ruolo-only, nessuno scoping ownership: mirror esatto di
     * QuotePolicy::view(). Il dettaglio di un Task è visibile a chiunque
     * abbia un ruolo abilitato, anche se non è owner della Quote né
     * creatore del Task — la lista (GET /api/tasks) resta invece filtrata
     * via Task::scopeForUser() nel controller.
     */
    public function view(User $user, Task $task)
    {
        return true;
    }

    /**
     * Nessun vincolo di ownership sulla Quote: chiunque abbia un ruolo
     * abilitato (già garantito da before()) può creare un Task su
     * qualsiasi Quote, mirror della regola Nova esistente (oc:8327).
     * Unica eccezione, introdotta in Fase: challenge di questo ticket:
     * la Quote non deve essere closed_won/closed_lost, stesso vincolo già
     * applicato da QuotePolicy::update() sulla Quote stessa — evita
     * follow-up su trattative già chiuse.
     *
     * $quote è opzionale: Nova chiama Gate::authorize('create', Task::class)
     * con un solo argomento (nessuna istanza Quote) per decidere se
     * mostrare l'azione "crea" sulla risorsa — senza questo default,
     * quella chiamata generica solleva un ArgumentCountError fatale e
     * rompe la creazione di Task via Nova per chiunque. Quando $quote è
     * assente il blocco non si applica (comportamento Nova invariato,
     * pre-esistente a questo ticket); il nostro controller API passa
     * sempre la Quote esplicitamente, quindi il blocco resta effettivo
     * lì.
     */
    public function create(User $user, ?Quote $quote = null)
    {
        if ($quote === null) {
            return true;
        }

        return $quote->status !== QuoteStatus::Closed_Won->value
            && $quote->status !== QuoteStatus::Closed_Lost->value;
    }

    /**
     * Autorizzazione "base" per il PATCH: copre il campo `notes` (chiunque
     * abbia un ruolo abilitato può aggiungere una nota, mirror di
     * Story::addDevNote() dove qualunque utente autorizzato può annotare).
     * Il campo `status` NON è coperto qui: richiede il check aggiuntivo
     * updateStatus(), invocato esplicitamente da TaskController::update()
     * SOLO quando il payload contiene la chiave `status`, PRIMA di
     * applicare qualsiasi modifica — questo realizza il comportamento
     * "tutto o niente" quando un payload misto {status, notes} arriva da
     * un utente che non è il creator: la richiesta fallisce con 403 prima
     * che `notes` venga persistito. Questa autorizzazione differenziata
     * per campo diverge intenzionalmente dal pattern "un verdetto per
     * endpoint" usato da QuotePolicy — decisione presa in Fase: challenge
     * di oc:8403.
     */
    public function update(User $user, Task $task)
    {
        return true;
    }

    /**
     * Solo il creatore del Task può cambiarne lo status (mirror esatto di
     * App\Nova\Actions\ToggleTaskCompleted::authorizedToRun()). Un Task
     * con creator_id nullo (utente creatore eliminato, nullOnDelete) non è
     * completabile/riapribile da nessuno — limite ereditato dal
     * comportamento Nova esistente, non introdotto da questa API.
     */
    public function updateStatus(User $user, Task $task)
    {
        return $task->creator_id !== null && $task->creator_id === $user->id;
    }
}
