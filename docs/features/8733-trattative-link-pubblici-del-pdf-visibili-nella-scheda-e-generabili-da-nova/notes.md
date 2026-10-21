> Ticket: oc:8733

# Notes — Trattative: link pubblici del PDF visibili nella scheda e generabili da Nova

## Divergenze dal piano, task per task

### Task 5 — Link attivi e Storico nella tab Main

Il piano metteva l'avvertenza «Il link apre sempre la versione attuale del preventivo» in
`->help()` del campo Storico. Nella prova manuale non compariva: Nova mostra `help` solo nei form,
non nella pagina di dettaglio, dove i due campi vivono (`onlyOnDetail`). L'avvertenza è ora una riga
in coda al contenuto dello Storico, testata in `GenerateQuotePublicPdfLinkActionTest`.

In prima stesura il link compariva come «Apri», senza l'indirizzo visibile: scelta non annotata,
segnalata come bloccante dalla review del 20/10/2026, perché il cliente chiede di vedere i link «con
l'indirizzo e la data di scadenza». Ora sotto lingua e scadenza c'è l'URL per intero come testo del
link (`break-all`, va a capo), e un test lo verifica; tolta la chiave di traduzione «Open».

## Bug trovati

- Un'esecuzione di `GenerateQuotePublicPdfLinkActionTest` è fallita una volta con un errore di
  vincolo unico e non si è più ripresentata in 10 esecuzioni. Causa probabile: `UserFactory` genera
  l'email con `fake()->unique()->safeEmail()`, unica solo dentro il processo, mentre `users.email` è
  unica nel DB di test che contiene già utenti. Comportamento della factory, preesistente.

## Decisioni

- **Link solo in Nova, non nell'API.** Il ticket prevede l'esposizione via API solo come
  possibilità («se esposti … seguono la stessa restrizione»); la richiesta del cliente e la call
  del 13/10 parlano della scheda in Nova. L'estensione via `?include=pdfLinks` è descritta in
  `overview.md`, «Out of scope».
- **L'URL generato da Nova si mostra nel messaggio di conferma.** Il ticket dice «Alla conferma
  salva il link e mostra l'URL da copiare» e, per la scheda, elenca solo Link attivi e Storico.
  Scartati il campo «Ultimo link generato» con bottone copia (non chiesto dal ticket) e il popup
  dedicato con `Action::modal()` (componente Vue scritto a mano in `nova-components/`, più lavoro
  per un'action che resta la strada di riserva rispetto alla skill Claude). Passare al popup in
  seguito non richiede di cambiare i dati salvati.
- **Link generabili anche dalle trattative chiuse, quindi action anche in «Archived quotes»**
  (challenge del 19/10/2026). Il ticket non distingue fra trattative aperte e chiuse. Si è scelto
  di allinearsi all'endpoint, che già genera link su qualunque stato: per esempio una trattativa
  vinta di cui il cliente chiede di nuovo il PDF. Scartata l'alternativa «solo trattative aperte»,
  che avrebbe fatto comportare Nova e API in modo diverso.
- **Stima del 20/10/2026.** `wm-estimate`, sul piano: Misurato 3,6h (ore registrate sul ticket in
  Orchestrator) + Stimato 3,4h = 7h. La dev l'ha sostituita con 4h, scritte in `estimated_hours`.
- **Verifica manuale in Nova locale (20/10/2026), da Admin.** Action «Genera link pubblico» dal
  menu «…» del dettaglio: popup con avviso sulla revoca, lingua «Italiano» e 30 giorni di default;
  dopo l'esecuzione il messaggio mostra URL e scadenza, e la scheda mostra il link in «Link attivi» e
  nello «Storico dei link» con data e autore. Stessa prova su una trattativa «Chiuso Vinto» aperta da
  «Preventivi archiviati». L'URL salvato apre il PDF senza sessione (200, `application/pdf`), con
  firma alterata risponde 403. La prova con un utente Developer non è stata fatta da browser (serve
  un accesso con quel ruolo): è coperta dai test su `canSee`/`canRun` e `canSeePdfLinks`.

- **Review formale del codice con `wm-review-ticket` (20/10/2026), prima del commit.** Un bloccante,
  corretto: la scheda mostrava «Apri» invece dell'indirizzo (vedi divergenza del Task 5). Cleanup
  applicati: la regola «solo Admin e Manager» è una sola, `QuotePdfLinks::canShare()`, usata da
  endpoint, action e campi; i default (lingua `it`, 30 giorni, massimo 90) sono costanti del servizio,
  e l'action non ha più fallback che non scattavano mai; il test sui campi della scheda risolve i
  campi del dettaglio per ruolo (provato togliendo `->canSee()` da un campo: il test fallisce).
  Non applicati: HTML dei link costruito nella risorsa Nova e regola «link attivo» solo lì; SQL
  specifico di PostgreSQL nel servizio; test della trattativa chiusa via `handle()` invece che dalla
  richiesta `archived-quotes` (coperto dalla prova manuale); docblock del controller in inglese come
  il resto del blocco pubblicato da Scramble; link separati da a capo invece che da « · », testo
  dell'avviso «non si possono revocare», `onlyOnDetail()` oltre a `sole()`.
- **Test:** `QuoteNovaResourceTest` verde; suite completa 715 test verdi sul DB di test (il branch
  non contiene ancora i test di oc:8729). Documentazione Scramble di `POST /quotes/{quote}/pdf-link`
  invariata (`expires_in_days` 1–90, `lang` max 5).
