> Ticket: oc:8733

# Notes — Trattative: link pubblici del PDF visibili nella scheda e generabili da Nova

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
