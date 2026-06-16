> Ticket: oc:8040

# Invio email a tutti i dev alla creazione di qualsiasi ticket

## Cosa cambia

Quando un ticket viene creato da **qualsiasi utente** (developer, admin, manager, customer), tutti i developer ricevono l'email di notifica via `CustomerNewStoryCreated`. In precedenza l'email veniva inviata solo quando il creatore aveva il ruolo `Customer`.

La mail class è unica per tutti i casi. Il link Nova e il corpo dell'email si adattano al ruolo del creator tramite la proprietà `$novaUrl` calcolata nel costruttore.

## Perché

Un developer che apre un ticket sta facendo una richiesta, esattamente come un customer. Il team vuole visibilità completa su ogni nuova creazione. Un'unica mail class riduce la duplicazione e semplifica la manutenzione.

## Requisiti

- [x] Alla creazione di qualsiasi ticket, tutti i developer ricevono l'email
- [x] Unica mail class `CustomerNewStoryCreated` per tutti i casi
- [x] Corpo: mostra `customer_request` se non vuoto, altrimenti nulla
- [x] Link Nova: `/resources/customer-stories/{id}` se creator è Customer, `/resources/stories/{id}` altrimenti
- [x] Il developer creatore riceve l'email come tutti gli altri (non viene escluso)

## Rischi

- **Aumento volume email**: i developer riceveranno email anche per ticket aperti da altri developer. Accettato — è l'obiettivo esplicito della feature.
- **Invio sincrono**: `Mail::send()` è sincrono — con molti developer la request può rallentare se il mail server è lento. Tech debt noto, fuori scope.

## Out of scope

- Conversione a invio asincrono via queue
- Notifiche per admin/manager

## Moduli toccati

- `app/Models/Story.php` — hook `created()`: usa sempre `CustomerNewStoryCreated`
- `app/Mail/CustomerNewStoryCreated.php` — aggiunta proprietà `$novaUrl` calcolata dal ruolo del creator
- `resources/views/mails/customer-new-story-created.blade.php` — corpo condizionale su `customer_request`, link dinamico via `$novaUrl`
- `tests/Feature/StoryEmailTriggersTest.php` — test per entrambi i casi (dev creator e customer creator)
