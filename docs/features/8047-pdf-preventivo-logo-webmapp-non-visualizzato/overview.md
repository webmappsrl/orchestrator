> Ticket: oc:8047

# PDF preventivo – logo Webmapp non visualizzato nell'intestazione

## Cosa cambia
Il template Blade del PDF preventivo smette di usare `logo.svg` come data URI in un tag `<img>` e passa a `logo.png` referenziato tramite protocollo `file://`. DomPDF carica correttamente le immagini locali via `file://`; sia SVG come data URI che PNG come data URI non venivano renderizzati in questo setup.

## Perché
DomPDF (`barryvdh/laravel-dompdf ^3.0`) non renderizza né SVG né PNG passati come `data:...;base64,...` in un tag `<img>` in questo setup (PDF generato di test risultava 1131 byte, immagine assente). Il protocollo `file://` è nella whitelist di `allowed_protocols` in `config/dompdf.php` e funziona correttamente.

## Requisiti
- [x] Aggiungere `public/images/logo.png` al repository (400×358px, fornito dal team e ridimensionato da 2400px)
- [x] Aggiornare il template `resources/views/quote-pdf.blade.php` per caricare `logo.png` via `file://` invece di `logo.svg` come data URI
- [x] Il logo deve seguire lo stile CSS già presente (nessuna modifica al CSS)
- [x] Il fallback (logo nascosto se il file non esiste) deve essere mantenuto
- [x] Verificare visivamente il PDF generato — logo visibile confermato

## Rischi
- **File non ancora presente:** `logo.png` non è ancora nel repo — il fix richiede che il file venga fornito e committato insieme alla modifica del template. Se il file manca, il logo resta nascosto (fallback silenzioso già esistente).
- **Dimensioni PNG:** passando da SVG (vettoriale) a PNG (raster), se il file fornito ha risoluzione bassa il logo apparirà sfocato. Mitigazione: verificare visivamente il PDF generato dopo il deploy.

## Out of scope
- Posizionamento del logo nell'intestazione/piè di pagina (affrontato in un ciclo successivo)
- Stile o dimensioni del logo (si mantiene il CSS attuale)
- Header/footer ripetuti su ogni pagina via CSS `position: fixed`

## Moduli toccati
- `resources/views/quote-pdf.blade.php` — sostituzione del path e del MIME type del logo
- `public/images/logo.png` — nuovo file da aggiungere al repository
