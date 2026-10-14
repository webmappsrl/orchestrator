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
     * Unico punto in cui sono elencati i ruoli che accedono ai Task: lo
     * usano before(), le opzioni del campo Assegnatario in Nova e la
     * validazione di `assignee_id` nell'API (oc:8722). Per aprire i task a
     * un altro ruolo si cambia solo qui.
     */
    public const ROLES = [UserRole::Admin, UserRole::Manager, UserRole::Developer];

    /**
     * Stesso perimetro ruoli di QuotePolicy: solo Admin, Manager, Developer
     * possono accedere alle API Task, indipendentemente dall'ability.
     */
    public function before(User $user)
    {
        if (! $user->canAccessTasks()) {
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
     * abbia un ruolo abilitato, anche se non ne è assegnatario né
     * creatore — la lista (GET /api/tasks) resta invece filtrata
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
     * Autorizzazione "base" per il PATCH: copre i campi `notes` e
     * `due_date` (chiunque abbia un ruolo abilitato può aggiungere una nota,
     * mirror di Story::addDevNote() dove qualunque utente autorizzato può
     * annotare, e riprogrammare la scadenza, come già in Nova — oc:8625).
     * Il campo `status` ha un check a sé, updateStatus(), invocato da
     * TaskController::update() SOLO quando il payload contiene la chiave
     * `status`, PRIMA di applicare qualsiasi modifica (oc:8403). Da oc:8722
     * updateStatus() è aperto a chiunque acceda ai task, quindi oggi i due
     * check coincidono; la separazione resta come punto in cui restringere
     * di nuovo il cambio di stato senza toccare `notes` e `due_date`.
     */
    public function update(User $user, Task $task)
    {
        return true;
    }

    /**
     * Chiunque acceda ai Task può completarli e riaprirli, non più solo il
     * creatore (oc:8722: «Chiunque crea i task e chiunque li può chiudere»).
     * Il filtro sui ruoli è in before(); stessa regola di
     * App\Nova\Actions\ToggleTaskCompleted::authorizedToRun(). Resta un
     * metodo a sé perché TaskController::update() lo invoca solo quando il
     * payload contiene `status`.
     */
    public function updateStatus(User $user, Task $task)
    {
        return true;
    }
}
