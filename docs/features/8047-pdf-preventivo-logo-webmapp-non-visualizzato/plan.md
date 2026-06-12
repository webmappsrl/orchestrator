> Ticket: oc:8047

# Plan — PDF preventivo: logo Webmapp non visualizzato

## Contesto

Bug fix su `resources/views/quote-pdf.blade.php`. DomPDF non renderizza SVG come data URI in tag `<img>` — mostra l'alt text al posto dell'immagine. La soluzione è passare a PNG, già supportato da DomPDF.

Un singolo commit include entrambi i file (`logo.png` + template aggiornato).

---

## Step 1 — Aggiungere `public/images/logo.png`

Copiare il file PNG del logo nella directory `public/images/`. Il file deve essere fornito dal team prima di procedere.

**Verifica:** `file_exists(public_path('images/logo.png'))` deve ritornare `true`.

---

## Step 2 — Aggiornare `resources/views/quote-pdf.blade.php`

Sostituire il blocco logo nel blocco PHP dell'header con:

```php
$logoPath = public_path('images/logo.png');
$logoSrc = file_exists($logoPath) ? 'file://' . $logoPath : '';
```

E aggiornare il markup:

```html
@if($logoSrc)
<div class="logo">
    <img src="{{ $logoSrc }}" alt="webmapp logo">
</div>
@endif
```

Modifiche rispetto al codice originale:
- Path: `logo.svg` → `logo.png`
- Approccio: data URI base64 → `file://` path diretto (DomPDF non renderizza data URI PNG in questo setup)
- Logica semplificata: rimosso `file_get_contents` e `base64_encode`

---

## Step 3 — Verifica visiva

Generare il PDF dal Preventivo 206 (`/resources/quote-no-filters/206`) e verificare che il logo appaia correttamente nell'intestazione su tutte le pagine.

---

## Step 4 — Commit

```
fix(oc:8047): use PNG instead of SVG for logo in quote PDF
```

Includere nel commit:
- `public/images/logo.png`
- `resources/views/quote-pdf.blade.php`
- `docs/features/8047-pdf-preventivo-logo-webmapp-non-visualizzato/`

---

## Step 5 — PR verso `develop`

Aprire PR con titolo: `fix(oc:8047): use PNG instead of SVG for logo in quote PDF`
