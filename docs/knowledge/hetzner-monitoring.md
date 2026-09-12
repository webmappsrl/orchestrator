# Monitoraggio dei costi Hetzner

> Origine: oc:7944

Dashboard Nova con una tabella per progetto Hetzner (server, floating IP, volumes, load balancer,
snapshot) ed export CSV.

## Stato attuale

- **I token stanno in ENV** con la convenzione `HETZNER_TOKEN_<SLUG>`, letti dinamicamente da
  `config/hetzner.php` via `collect($_ENV)`. Aggiungere un progetto significa aggiungere una
  variabile ENV e riavviare il container: nessun deploy di codice.
- **Gli errori sono isolati per progetto**: un token non valido non blocca gli altri. La cache Redis
  è per progetto (`hetzner_project_{slug}`, TTL 15 minuti).
- **I prezzi di Volumes e Snapshots sono hardcodati** in `HetznerApiService`: l'API Hetzner Cloud non
  espone il pricing per queste risorse. Valori da documentazione pubblica (mag 2026): Volumes
  €0,0476/GB/mese, Snapshots €0,0119/GB/mese. Vanno aggiornati a mano se Hetzner cambia listino.
- Il componente Nova è self-contained, vedi [Nova components: bundle scritti a mano](nova-components-bundle.md).
