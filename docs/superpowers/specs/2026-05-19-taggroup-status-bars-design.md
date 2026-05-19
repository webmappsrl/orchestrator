# TagGroup Status Bars — Design Spec

**Goal:** Mostrare visivamente la distribuzione degli stati dei ticket nei TagGroup, sia sull'indice che nella vista dettaglio.

**Date:** 2026-05-19

---

## Contesto

I TagGroup raggruppano ticket (Story) tramite condizioni a tag. Attualmente l'indice mostra solo SAL # e SAL t (metriche numeriche). La vista dettaglio mostra i tag nelle condizioni come testo semplice. Non c'è visibilità immediata su come sono distribuiti gli stati dei ticket.

---

## Funzionalità 1 — Stacked bar sull'indice

### Dove
Nuova colonna `Text::make` con `asHtml()` nell'indice dei TagGroup, posizionata dopo SAL # e SAL t.

### Comportamento
- Recupera tutti i ticket del TagGroup (`$this->stories()->get()->groupBy('status')`)
- Renderizza una barra orizzontale (160px) con un segmento per ogni stato presente, larghezza proporzionale alla percentuale
- Colori: `StoryStatus::color()` già definito nell'enum
- Tooltip al hover: elenco degli stati non-zero con label e conteggio (es. "Progress: 3 | Done: 5 | Backlog: 2")
- Se 0 ticket → mostra `—`
- Stati con 0 ticket non appaiono nella barra

### HTML generato (esempio)
```html
<div title="Progress: 3 | Done: 5 | Backlog: 2"
     style="display:flex;width:160px;height:14px;border-radius:4px;overflow:hidden;gap:1px;">
  <div style="width:30%;background:#2563EB"></div>
  <div style="width:50%;background:#16A34A"></div>
  <div style="width:20%;background:#9CA3AF"></div>
</div>
```

---

## Funzionalità 2 — Stacked bar per tag nella vista dettaglio

### Dove
I campi Gruppo 1-4 nella vista dettaglio del TagGroup (metodo `conditionFieldsForIndex()` in `app/Nova/TagGroup.php`). Attualmente mostrano solo il nome del tag come testo. In vista dettaglio vengono arricchiti.

### Comportamento
- Ogni tag nella condizione viene reso come:  
  `[Nome tag]  [stacked bar 120px]  N tickets`
- La stacked bar usa la stessa logica e gli stessi colori della Funzionalità 1
- Per tag normali: query `Story::whereHas('tags', fn($q) => $q->where('tags.id', $tagId))`
- Per TagGroup annidati (prefisso `g:`): `TagGroup::find($id)->stories()`
- Il conteggio mostra il numero totale di ticket del tag (non filtrati per questo TagGroup)
- Se un tag ha 0 ticket → mostra solo il nome senza barra
- In vista indice il comportamento rimane invariato (solo testo)

### HTML generato per riga (esempio)
```html
<div style="display:flex;align-items:center;gap:12px;padding:4px 0;">
  <span style="min-width:200px;">[26Q2][ParchiEmiliaCentrale]</span>
  <div title="Progress: 3 | Done: 5" style="display:flex;width:120px;height:12px;border-radius:3px;overflow:hidden;gap:1px;">
    <div style="width:37.5%;background:#2563EB"></div>
    <div style="width:62.5%;background:#16A34A"></div>
  </div>
  <span style="color:#6B7280;font-size:0.8rem;">8 tickets</span>
</div>
```

---

## File coinvolti

| File | Modifica |
|------|----------|
| `app/Nova/TagGroup.php` | Aggiungere colonna stacked bar in `fields()` (indice) + arricchire `conditionFieldsForIndex()` per dettaglio |
| `app/Nova/Metrics/TagGroupTicketsByStatus.php` | Nessuna modifica |
| `app/Models/TagGroup.php` | Nessuna modifica |

---

## Fuori scope
- Nessun componente Vue custom
- Nessuna nuova migration
- Nessuna modifica alle metriche esistenti (TagSal, TagGroupTicketsByStatus, TagGroupTicketsByType)
