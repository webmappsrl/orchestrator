> Ticket: oc:8136

# Auto-revert progress tickets when developer goes offline on Slack

## Cosa cambia
Un nuovo comando schedulato gira ogni 20 minuti a partire dalle 12:00. Per ogni developer che ha almeno un ticket in "progress", verifica la presenza su Slack via API (`users.getPresence`). Se il developer risulta offline (`away`), tutti i suoi ticket in progress vengono riportati in "todo" silenziosamente (senza notifiche email né trigger observer). Il comando esistente `story:progress-to-todo` (revert globale alle 18:00) rimane come safety net.

## Perché
I developer a volte dimenticano di aggiornare i ticket a fine giornata. Il revert globale delle 18:00 è troppo blunt — non distingue chi sta ancora lavorando da chi ha già smesso. Il nuovo meccanismo usa la presenza Slack come segnale reale di attività, rendendo il revert più preciso e contestuale.

## Requisiti
- [ ] Aggiungere colonna `slack_user_id` (nullable string) al modello `User` con relativa migration
- [ ] Esporre il campo `slack_user_id` come editabile nella Nova Resource `User`
- [ ] Creare `SlackService` che wrappa le chiamate all'API Slack (`users.getPresence`)
- [ ] Aggiungere `SLACK_BOT_TOKEN` alle variabili ENV e a `config/services.php`
- [ ] Creare il comando `story:slack-revert-progress` che:
  - Recupera tutti i developer con almeno un ticket in "progress" E con `slack_user_id` valorizzato
  - Per ciascuno chiama `users.getPresence` via `SlackService`
  - Se `presence == away` → aggiorna il suo ticket in progress a "todo" via `saveQuietly()` (normalmente è uno solo; il comando gestisce comunque il caso edge di più ticket in progress)
- [ ] Schedulare il comando ogni 20 minuti dalle 12:00 alle 18:00 in `Kernel.php`
- [ ] Aggiungere test Feature per il comando (mock SlackService)
- [ ] Creare StoryLog manualmente dopo `saveQuietly()` per ogni ticket revertato, usando `orchestrator_artisan@webmapp.it` come user di sistema (stessa logica di `StoryObserver::createStoryLog()`)

## Rischi
- **Token Slack non configurato:** se `SLACK_BOT_TOKEN` è assente o invalido, il comando non deve bloccarsi — loggare l'errore e continuare (fail-soft per developer).
- **`slack_user_id` non valorizzato:** developer senza ID Slack vengono semplicemente saltati — il revert globale delle 18:00 li copre come fallback.
- **Rate limit Slack:** `users.getPresence` ha un limite di 50 req/min (Tier 3). Con ~15 developer e frequenza 20 min è ampiamente nel limite.
- **Errore API Slack ≠ "offline":** in caso di eccezione o risposta non valida dall'API Slack, il developer viene saltato — non revertato. Solo `presence == away` esplicito triggera il revert.
- **Presenza inaccurata:** Slack può mostrare `active` anche se il dev ha solo il browser aperto in background. Non è un problema critico — il revert delle 18:00 rimane il safety net definitivo. I developer Webmapp risultano sempre `active` durante la giornata lavorativa (anche in call), quindi i falsi positivi sono attesi minimi.

## Out of scope
- Notifiche al developer quando i suoi ticket vengono revertati
- Configurazione `slack_user_id` tramite self-service (lo imposta un admin da Nova)
- Supporto a workspace Slack multipli
- Sostituzione del comando `story:progress-to-todo` esistente (rimane invariato)

## Moduli toccati
- `database/migrations/<timestamp>_add_slack_user_id_to_users_table.php` — nuovo
- `app/Models/User.php` — aggiunta `slack_user_id` a `$fillable`
- `app/Nova/User.php` — campo `slack_user_id` editabile
- `app/Services/SlackService.php` — nuovo
- `config/services.php` — aggiunta chiave `slack`
- `app/Console/Commands/SlackRevertProgressCommand.php` — nuovo
- `app/Console/Kernel.php` — scheduling del nuovo comando
- `tests/Feature/SlackRevertProgressCommandTest.php` — nuovo
