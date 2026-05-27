> Ticket: oc:7944

# Ottimizzazione Costi Hetzner

## Cosa cambia
Orchestrator espone una nuova view "Hetzner Monitoring" che aggrega in tempo reale (con cache Redis) i dati di tutti i progetti Hetzner Cloud aziendali, mostrando per ogni progetto: server con status e costo, Floating IP, Volumes, Load Balancers e Snapshots. La view include un export CSV e un pulsante di refresh manuale della cache.

## Perché
I costi mensili dei server Hetzner superano €1000. Molte macchine sono inutilizzate o sovraddimensionate. Senza una visione aggregata dei progetti è impossibile identificare rapidamente cosa eliminare o ridimensionare. La feature nasce su richiesta del management per avere un quadro completo e aggiornato dell'infrastruttura Hetzner come base per decisioni di ottimizzazione.

## Requisiti
- [ ] Leggere i token Hetzner da variabili d'ambiente con convenzione `HETZNER_TOKEN_<SLUG>` e mapparli in `config/hetzner.php`
- [ ] Per ogni progetto Hetzner, recuperare via API Cloud: server (nome, status, tipo, specs, prezzo mensile), Floating IP (prezzo, server assegnato), Volumes (dimensione, prezzo, server assegnato), Load Balancers (tipo, prezzo, targets), Snapshots (dimensione, prezzo)
- [ ] Cachare i dati per progetto su Redis con TTL 15 minuti
- [ ] Esporre una Nova Tool "Hetzner Monitoring" con una tabella per progetto
- [ ] Mostrare status server con indicatore visivo (verde = running, grigio = off, giallo = transient)
- [ ] Calcolare costo stimato mensile per risorsa e totale per progetto (prezzi da API, con nota esplicita che sono prezzi di listino, escluse eventuali promozioni o crediti)
- [ ] Evidenziare visivamente risorse "sprecate": server off, Floating IP non assegnati, Volumes non montati
- [ ] Bottone "Refresh" per forzare il rinnovo della cache di tutti i progetti
- [ ] Export CSV con tutti i dati visibili nella view
- [ ] Visibile a ruoli: Admin, Manager, Developer

## Rischi
- **Costo stimato ≠ fattura reale**: i prezzi esposti sono i prezzi di listino dell'API Cloud. Il billing reale (con sconti, crediti, trial) è su un endpoint separato. Mitigazione: nota esplicita in UI e nel CSV.
- **Token in ENV**: aggiungere un nuovo progetto richiede deploy. Accettato per questa iterazione — struttura `config/hetzner.php` predisposta per futura migrazione a DB.
- **Rate limiting**: ~10 progetti × 5-6 endpoint = 50-60 chiamate per refresh. Chiamate sequenziali per token, ben sotto il limite di 100 req/10s. Cache Redis previene chiamate eccessive.
- **Token nei log**: client Guzzle configurato senza logging degli header.

## Out of scope
- DigitalOcean (ciclo successivo)
- Mappatura DNS server
- Metriche di utilizzo CPU/RAM in tempo reale
- Gestione token via interfaccia Nova (DB) — rimane in ENV
- Storico costi / trend nel tempo
- Azioni di gestione server (stop, delete) da Orchestrator

## Moduli toccati
| File | Azione | Repo |
|---|---|---|
| `config/hetzner.php` | Crea | orchestrator |
| `app/Services/HetznerApiService.php` | Crea | orchestrator |
| `app/Nova/Tools/HetznerMonitoring.php` | Crea | orchestrator |
| `nova-components/hetzner-monitoring/` | Crea (Nova Tool Vue component) | orchestrator |
| `app/Providers/NovaServiceProvider.php` | Modifica (registra tool + menu) | orchestrator |
| `.env.example` | Modifica (aggiunge placeholder token) | orchestrator |
| `app/Exports/HetznerExport.php` | Crea | orchestrator |
| `routes/api.php` | Modifica (endpoint dati Hetzner per il Tool) | orchestrator |
