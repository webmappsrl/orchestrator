# CHANGELOG MS-1.21.11

**Release Date:** 04/01/2026  
**Version:** MS-1.21.11

## 🔧 Improvements

### Gestione Stati Ticket
- Rimossa opzione "Rejected" quando lo stato corrente è "TODO"
- Aggiunto campo tester obbligatorio quando si seleziona lo stato "TEST"
- Aggiunto stato "Released" come opzione disponibile quando si è in stato "TESTING"
- Modificati stati disponibili in "Progress": rimossa opzione "Rejected", mantenuto ordine specifico (Test, Released, Todo, Problem, Waiting)
- Il campo tester mostra automaticamente il tester già assegnato quando presente

### Gestione Fallimento Test
- Aggiunto campo "Ragione del fallimento del test" obbligatorio quando si passa da stato "Testing" a "TODO"
- La nota di fallimento viene automaticamente aggiunta in cima alle note di sviluppo con formato: "TEST FALLITO / [data/ora] / [Tester]. Descrizione del fallimento del test"

## 📋 Technical Details

### File Modificati
- `app/Nova/Actions/ChangeStatus.php` - Miglioramenti gestione stati e aggiunta campo fallimento test

