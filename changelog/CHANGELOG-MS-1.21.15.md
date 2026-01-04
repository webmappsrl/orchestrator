# CHANGELOG MS-1.21.15

**Release Date:** 04/01/2026  
**Version:** MS-1.21.15

## 🐛 Bug Fixes

### Interfaccia Utente - Validazione Campi
- **Campo "Ragione del fallimento del test" mostrato solo quando necessario** - Il campo "Ragione del fallimento del test" viene ora mostrato solo quando lo stato corrente del ticket è "Testing" e si seleziona "Todo" come nuovo stato
  - Il campo non viene più mostrato quando si passa da "Assegnato" a "Todo"
  - Il campo viene mostrato correttamente solo quando si passa da "Testing" a "Todo"
  - La validazione rimane invariata: il campo è obbligatorio solo quando si passa da Testing a Todo

## 📋 Technical Details

### File Modificati
- `app/Nova/Actions/ChangeStatus.php` - Modificata la logica di visualizzazione del campo `test_failure_reason` nel metodo `dependsOn()` per verificare lo stato corrente del ticket prima di mostrare il campo

### Database
- Nessuna migrazione richiesta

