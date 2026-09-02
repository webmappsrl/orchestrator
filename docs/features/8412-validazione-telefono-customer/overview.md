> Ticket: oc:8412

# Validazione e helper per campi telefono/cellulare Customer

## Cosa cambia
Nel form Nova di creazione/modifica Customer (`app/Nova/Customer.php`), i campi "Telefono" (`phone`) e "Cellulare" (`mobile_phone`) passano da una validazione basata su regex generica a un controllo strutturale di plausibilità, scritto internamente al progetto (nessuna libreria esterna): un numero con prefisso `+XX` esplicito deve avere 8-15 cifre (limiti E.164), un numero senza prefisso è assunto italiano e deve avere 6-11 cifre. Il supporto multi-numero (più numeri separati da virgola nello stesso campo) resta invariato: ogni frammento viene validato singolarmente. L'help text sotto entrambi i campi mostra un esempio concreto di formato riconosciuto (`+39 328 5360803`).

## Perché
Oggi i due campi accettano quasi qualsiasi carattere (la regex attuale ammette cifre, spazi, `+`, virgole, trattini, punti, parentesi e alcuni caratteri unicode invisibili) senza indicare all'utente un formato di riferimento né verificare che il numero sia realmente valido. Il cliente ha chiesto un helper con esempio di formato e una validazione che segnali errori comprensibili.

## Requisiti
- [ ] Nessuna dipendenza composer esterna: la validazione è un controllo strutturale nativo (regex + conteggio cifre), non una validazione semantica per-piano-nazionale
- [ ] Sostituire la regex attuale sui campi `phone` e `mobile_phone` in `app/Nova/Customer.php` con il nuovo controllo di plausibilità (`isValidPhoneFragment()`)
- [ ] Mantenere il supporto multi-numero (separati da virgola): validare ogni frammento del campo come numero telefonico indipendente. Frammenti vuoti/solo-spazi dopo lo split (es. virgola finale o doppia virgola) vengono scartati silenziosamente, senza generare errore
- [ ] Country di default per numeri senza prefisso internazionale esplicito: `IT`, implementato come range di cifre plausibile (6-11) anziché come validazione di un vero piano di numerazione nazionale. Un numero con prefisso `+XX` esplicito viene validato secondo i limiti E.164 generali (8-15 cifre), indipendentemente dal default
- [ ] Nessuna distinzione tra numero mobile e fisso tra i due campi: sia "Telefono" che "Cellulare" accettano qualsiasi numero valido (mobile o fisso)
- [ ] In caso di errore, messaggio generico sul campo (non identifica quale frammento della lista è invalido), es: "Uno o più numeri inseriti non sono in un formato telefonico valido. Esempio: +39 328 5360803"
- [ ] Aggiornare l'help text di entrambi i campi con l'esempio concreto `+39 328 5360803`, tradotto in `lang/it.json` e `lang/en.json` seguendo la convenzione esistente (chiave = testo inglese). L'esempio del messaggio d'errore e quello dell'help text riusano la stessa chiave di traduzione, per evitare drift tra i due testi
- [ ] La validazione si applica **solo quando il valore del campo viene effettivamente modificato** rispetto al valore già in DB: un Customer con un numero legacy in formato non più valido resta salvabile su altri campi senza essere bloccato dalla nuova regola sul telefono invariato. Il confronto avviene su valori **normalizzati** (trim + collasso spazi multipli), non sulla stringa raw: una modifica di sola formattazione (es. spazio aggiunto/rimosso) non è considerata un cambiamento
- [ ] Prima del confronto "changed" e prima della validazione, entrambi i valori (nuovo e quello in DB) vengono ripuliti dai caratteri unicode invisibili già tollerati dalla regex attuale (zero-width space/joiner, BOM: `\x{200B}\x{200C}\x{200D}\x{FEFF}`) — preserva la tolleranza esistente evitando falsi negativi su numeri legittimi copia-incollati
- [ ] La logica di split/normalizzazione/validazione dei frammenti è estratta in un helper privato condiviso tra i campi `phone` e `mobile_phone` in `app/Nova/Customer.php` (stesso pattern di estrazione già in uso nel progetto, es. `statusBadgeField()` in `app/Nova/Task.php`), per evitare duplicazione e drift futuro tra i due campi

## Rischi
- **Il controllo è un sanity-check strutturale, non una validazione semantica**: a differenza di una libreria come `libphonenumber`, non verifica che un numero sia realmente assegnabile secondo il piano di numerazione di un paese — solo che abbia un conteggio di cifre plausibile. Conseguenza accettata consapevolmente (vedi decisione CTO): un input come `"12345678"` (8 cifre, nessun prefisso) viene accettato pur non essendo un numero italiano reale — possibili falsi positivi (numeri sintatticamente plausibili ma inesistenti), mai falsi negativi bloccanti su input palesemente non numerici (lettere, simboli).
- **Numeri VoIP/aziendali o extra-UE non standard con prefisso esplicito**: un numero con prefisso `+XX` e un conteggio cifre fuori dal range 8-15 viene respinto anche se realmente in uso (caso raro, dato il range ampio). Rischio accettato: riguarda solo **nuovi** inserimenti (i numeri legacy sono protetti dalla regola "solo se cambiato").
- **Nessuna vera distinzione per paese**: il range 6-11 cifre per i numeri senza prefisso è una scelta di comodo per l'Italia, non una regola realmente informata dal piano di numerazione nazionale (che ha vincoli più specifici per tipo di numero/area). Un cliente estero che inserisce un numero locale del proprio paese senza prefisso viene validato con le stesse regole italiane — limite noto e accettato, coerente con la scelta di non introdurre una dipendenza esterna per un caso d'uso a bassa criticità.
- **Validazione "solo se cambiato"**: richiede una regola custom che confronta il valore inviato con quello persistito in DB per il singolo Customer in edit (in creazione non c'è valore preesistente, la validazione si applica sempre). Il confronto avviene su valori normalizzati (trim + collasso spazi + strip caratteri invisibili), non sulla stringa raw — vedi Requisiti. Accettato senza azione, dato il basso blast radius.
- **Nessun impatto su dati esistenti**: la validazione si applica solo in scrittura tramite il form Nova; nessun backfill o rivalidazione retroattiva dei dati già in produzione.

## Out of scope
- Nessuna modifica ai dati già presenti in produzione (nessun backfill/normalizzazione)
- Nessuna distinzione tra numero mobile e fisso tra i due campi
- Nessun impatto sull'API pubblica: `Api/CustomerController` espone `phone` solo in lettura (`index`/`show`), nessun endpoint di scrittura Customer via API
- Nessun messaggio di errore che identifichi il frammento specifico non valido in un campo multi-numero

## Moduli toccati
- `app/Nova/Customer.php` — validazione (nativa, nessuna libreria esterna) e help text sui campi `phone` e `mobile_phone`
- `app/Models/Customer.php` — `normalizePhoneString()` reso `public` (era `protected`) per essere riusato da `Nova\Customer`, nessuna modifica di comportamento (vedi `notes.md` → Bug trovati)
- `lang/it.json`, `lang/en.json` — nuove chiavi di traduzione per l'help text aggiornato
- `tests/Feature/CustomerPhoneValidationTest.php` — copertura dei casi discussi in fase di challenge e review (22 test)
`docker-compose.yml` e `composer.json`/`composer.lock` **non sono tra i moduli toccati** da questo ticket: nessuna dipendenza esterna aggiunta (revisione post-review su richiesta del CTO). Il fix locale al tag immagine `phpfpm` (necessario per sbloccare l'ambiente durante lo sviluppo) è stato applicato solo al container Docker in esecuzione, non committato — resta debito pre-esistente e indipendente da questo ticket, da affrontare eventualmente in un commit/PR dedicato. Vedi `notes.md`.
