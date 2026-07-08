> Ticket: oc:8161

# Override accesso Nova in Orchestrator

## Cosa cambia

Orchestrator introduce una deroga locale al comportamento shared del `wm-package`: per questo progetto, l'ability `access-nova` viene sempre considerata concessa (`true`) tramite override in `App\Models\User::can()`.

Il blocco login web nel package resta invariato e continua a controllare `can('access-nova')`; la deroga opera solo nello shard Orchestrator.

## Perche'

La feature shared del package adotta un modello fail-closed (nega login web senza `access-nova`) valido per gli shard in cui esistono utenti finali non autorizzati a Nova.

Per Orchestrator il requisito e' diverso: tutti gli utenti applicativi devono poter entrare in Nova. La deroga evita di alterare il package condiviso e mantiene il comportamento specifico solo nel progetto.

## Requisiti

- [x] Nessuna modifica al `wm-package` per questa esigenza shard-specifica.
- [x] In Orchestrator `user->can('access-nova')` e' sempre `true`.
- [x] Tutte le altre permission/ability continuano a passare dal flusso standard Spatie/Gate.
- [x] Presente un test di regressione dedicato (`UserAccessNovaOverrideTest`).

## Moduli toccati

- `app/Models/User.php`
- `tests/Feature/UserAccessNovaOverrideTest.php`

## Rischi e note operative

- Se in futuro Orchestrator dovesse reintrodurre utenti senza accesso Nova, questa deroga va rimossa o resa configurabile.
- La documentazione del `wm-package` su oc:8161 resta corretta: il package e' fail-closed; la differenza e' intenzionalmente nello shard.
