# Ore stimate ed effettive

Quali misure di "ore effettive" esistono, quale leggere e dove finiscono. La mappa completa,
con disclaimer di data e commit, è in [docs/calcolo-ore-effettive-stimate.md](../calcolo-ore-effettive-stimate.md).

## Stato attuale

### Tre misure indipendenti, non intercambiabili (oc:8446)
| Misura | Sorgente | Filtro | Chi la legge |
|---|---|---|---|
| colonna `stories.hours` | `StoryTimeService`, da `StoryLog.created_at` | **solo lun-ven, 09:00-17:59** | Nova ("Effective Hours", metrica `StoryTime`), Tag SAL, `TagHoursTotal`, `estimationAccuracy()`, report e cache `reports:refresh-cache`, export Excel, **API pubblica** |
| `StoryMetricsCalculator::cycleTimeMinutes()` | `StoryLog.viewed_at` | nessuno, tempo di calendario | dashboard Team Performance, report PDF trimestrale |
| `Story::effectiveMinutes()` / `effectiveMinutesForStory()` | come sopra | nessuno | **nessuno** |

- **Non partire mai da `effectiveMinutes()`**: non ha mai avuto un chiamante. Il docblock oggi lo
  dice esplicitamente (`Story.php:598-601`: «Alimenta solo la dashboard Team Performance — NON è la
  fonte delle ore effettive»). Si legge `hours` o `cycleTimeMinutes()` a seconda del punto di
  consumo.
- **Le due misure in uso divergono nel 58% dei casi, con un fattore ~2x sulla media** (3242 story:
  media `hours` 267,8 min contro 564,4 min di calendario, scarto massimo ~36 giorni).
- **La finestra oraria è più stretta di quanto dice il commento**: `isAWorkingDate()` fa
  `$hour > 8 && $hour < 18`, quindi 08:00-08:59 è escluso e 17:00-17:59 incluso — il commento
  "8am to 6pm" è impreciso di un'ora.
- **`hours` non è interamente derivata dai log**: oltre a `Story::save()` e al comando
  `service:story-time` (manuale, non schedulato), è **scrivibile a mano da Nova** —
  `fieldTrait::effectiveHoursField()` ritorna un `Number` editabile e Nova bypassa `$fillable`.
  Un valore inserito a mano viene poi sovrascritto silenziosamente al successivo cambio di status.
- **La colonna ha un truthy-check, non un "not null" check**, in entrambi i path automatici: se il
  calcolo restituisce `0` l'update viene saltato e il valore precedente resta stantio invece di
  azzerarsi (coerente col dato reale: zero righe con `hours = 0`). Il calcolo non parte affatto se
  la story non ha un utente assegnato (`StoryTimeService::getStoryTime()` ritorna `false` in
  silenzio).
- **Tipi e unità divergono**: `hours` è `float` nullable in **ore**, arrotondato
  `round(min/60, 2)` **per singola story** (l'errore si accumula sommando N story); le altre due
  sono `?int` in **minuti**; `estimated_hours` è `decimal(5,2)`. Un rapporto fra `hours` ed
  `estimated_hours` (SAL%, `estimationAccuracy()`) mescola quindi `float` e `decimal`.
- **Stato reale dei dati** (locale, 2026-09-12): `hours` è `NULL` su **3971 story su 7538
  (53%)** — il null è il caso normale, non un edge case; **280 story hanno `hours` negativa**
  (fino a `-0.23`), non spiegata da alcuna anomalia nota e propagata silenziosamente da ogni
  `SUM()`; solo **99 story su 7537** hanno sia `hours` sia `estimated_hours`, cioè sono le sole su
  cui SAL% ed `estimationAccuracy()` producono un numero.
- L'aggregazione padre-figlio è in [Relazione padre-figlio fra Story](story-parent-child.md).

### Metrica "Todo >1g" in Team Performance (oc:8192)
- `StoryMetricsCalculator::todoStagnationTotalDays()` somma tutti gli intervalli in todo, esposta
  come colonna per ticket e come KPI aggregato.
- **`workingDaysBetween` conta giorni interi**: sotto un giorno lavorativo restituisce 0, ed è il
  motivo dell'etichetta "Todo >1g". I valori 0 si mostrano come `—`.
- **Cache Redis `team_perf_avg_{year}_q{quarter}`, TTL 1h**: dopo un deploy che aggiunge campi
  all'aggregato le chiavi vanno svuotate a mano (`Cache::forget(...)`), altrimenti il frontend
  riceve il vecchio JSON senza i nuovi campi.

## Come ci siamo arrivati

- **`effectiveMinutes()` come fonte autorevole**: nato in `34c59326` (oc:8123) nello stesso commit
  in cui la logica reale veniva scritta a parte in `StoryMetricsCalculator`, e mai ricollegato. Il
  suo docblock si **dichiarava** "fonte autorevole" delle ore effettive, ed era falso; oc:8446 lo ha
  riscritto nella forma oggi in `Story.php:598-601`, che rimanda esplicitamente alla colonna
  `hours`. La versione precedente del docblock non esiste più: non citarla come stato attuale.
- **oc:8446 non ha toccato il codice**: solo documentazione, con il deliverable in `docs/` (non in
  `docs/features/<slug>/`) per restare consultabile da più ticket. I riferimenti sono ancorati a
  nomi di metodo e non a numeri di riga, perché `Story.php` e `fieldTrait.php` sono fra i file più
  modificati del repo, e nessun meccanismo tiene il documento sincronizzato col codice.
