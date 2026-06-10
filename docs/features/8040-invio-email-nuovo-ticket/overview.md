> Ticket: oc:8040

# Invio email a tutti i dev alla creazione di qualsiasi ticket

## Cosa cambia

Quando un ticket viene creato da **qualsiasi utente** (developer, admin, manager, customer), tutti i developer ricevono l'email di notifica. In precedenza l'email veniva inviata solo quando il creatore aveva il ruolo `Customer`.

Per i ticket creati da non-customer viene introdotta una nuova mail class `DevNewStoryCreated` con template dedicato, separata da `CustomerNewStoryCreated` che rimane invariata.

## Perché

Un developer che apre un ticket sta facendo una richiesta, esattamente come un customer. Il team vuole visibilità completa su ogni nuova creazione. Le due mail class sono separate perché i contenuti differiscono: i developer compilano `description`, non `customer_request`, e il link alla story punta a una rotta Nova diversa.

## Requisiti

- [ ] Alla creazione di qualsiasi ticket, tutti i developer ricevono l'email
- [ ] Se il creatore è un `Customer`: viene usata `CustomerNewStoryCreated` (comportamento invariato)
- [ ] Se il creatore NON è un `Customer`: viene usata la nuova `DevNewStoryCreated`
- [ ] `DevNewStoryCreated`: corpo mostra `description`, con fallback a `customer_request`, con fallback a *"Nessun dettaglio aggiunto."*
- [ ] `DevNewStoryCreated`: link punta a `/resources/stories/{id}` (rotta Nova corretta per i developer)
- [ ] Il developer creatore riceve l'email come tutti gli altri (non viene escluso)
- [ ] `CustomerNewStoryCreated` e il suo template rimangono invariati

## Rischi

- **Aumento volume email**: i developer riceveranno email anche per ticket aperti da altri developer. Accettato — è l'obiettivo esplicito della feature.
- **`customer_request` vuoto per ticket dev**: mitigato dal fallback nel template `DevNewStoryCreated`.
- **Invio sincrono**: `Mail::send()` è sincrono — con molti developer la request può rallentare se il mail server è lento. Tech debt noto, fuori scope.

## Out of scope

- Rinomina di `CustomerNewStoryCreated` *(può essere fatto in un ciclo successivo senza impatto sulla logica)*
- Test con `Mail::fake()` sul flusso `CustomerNewStoryCreated` *(da aggiungere in ciclo successivo)*
- Conversione a invio asincrono via queue
- Notifiche per admin/manager

## Moduli toccati

- `app/Models/Story.php` — hook `created()`: aggiungere branch per non-customer con `DevNewStoryCreated`
- `app/Mail/DevNewStoryCreated.php` — nuova mail class
- `resources/views/mails/dev-new-story-created.blade.php` — nuovo template email
- `tests/Feature/StoryEmailTriggersTest.php` — test per dev-creator notifica tutti i dev
