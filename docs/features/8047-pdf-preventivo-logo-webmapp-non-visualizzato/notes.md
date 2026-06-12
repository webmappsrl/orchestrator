> Ticket: oc:8047

# Notes — PDF preventivo: logo Webmapp non visualizzato

## Deviazioni dal piano

**Approccio logo: data URI base64 → `file://` path**

Il piano originale prevedeva di codificare `logo.png` come `data:image/png;base64,...`. Durante i test, DomPDF non renderizzava l'immagine neanche con un PNG piccolo (400×358px) — il PDF di test risultava 1131 byte con immagine assente. Stesso comportamento con SVG.

Causa: DomPDF in questo setup non processa i data URI per le immagini nei tag `<img>`. La soluzione è usare il protocollo `file://` che è nella whitelist `allowed_protocols` di `config/dompdf.php`. Il risultato è anche codice più semplice (nessun `file_get_contents` / `base64_encode`).

## Bug trovati

- Il PNG originale fornito era 2400×2152px (131KB) — DomPDF non lo renderizzava anche con il metodo `file://` corretto (probabilmente per dimensioni eccessive). Ridimensionato a 400×358px (33KB) prima del commit.

## Decisioni

- **`file://` invece di data URI**: più affidabile con DomPDF, già nella whitelist della config. Non richiede lettura del file in PHP a runtime.
- **PNG ridimensionato a 400px**: dimensione adeguata per un logo in un PDF A4. Il file sorgente ad alta risoluzione rimane nel percorso originale del team (`/Volumes/Crucial X6/Marketing webmapp/logo.png`).

## Follow-up

- Posizionamento del logo nell'intestazione/piè di pagina (out of scope in questo ciclo, ticket oc:8047)
