# CHANGELOG MS-1.21.10

**Release Date:** 04/01/2026  
**Version:** MS-1.21.10

## 🔧 Improvements

### Documentazione Flusso Ticket
- Aggiunta dashboard TicketFlow in Nova con documentazione completa del flusso di evoluzione dei ticket
- Riorganizzata sezione transizioni per mostrare tutte le destinazioni possibili per ogni stato
- Documentate modalità di transizione (manuale/automatico) per ogni stato
- Aggiornate regole di transizione: Rejected raggiungibile solo da New
- Aggiunta transizione Testing → Released per rilasci diretti senza passare per Tested
- Rimossi Problem e Waiting dalle destinazioni di Testing, Tested e Released

### Sviluppo
- Aggiunto `.DS_Store` al `.gitignore` per ignorare file di sistema macOS

## 📋 Technical Details

### File Creati
- `app/Nova/Dashboards/TicketFlow.php` - Dashboard Nova per documentazione flusso ticket
- `resources/views/ticket-flow-documentation.blade.php` - View Blade con documentazione completa
- `docs/TICKET_STATUS_FLOW.md` - Documentazione markdown del flusso ticket

### File Modificati
- `app/Providers/NovaServiceProvider.php` - Registrazione dashboard TicketFlow nel menu Help
- `.gitignore` - Aggiunto `.DS_Store` per ignorare file di sistema macOS

## 📝 Notes

- La dashboard TicketFlow è accessibile tramite menu Nova: `Help > Flusso ticket`
- La documentazione include dettaglio completo di tutte le transizioni possibili per ogni stato
- Le transizioni sono chiaramente marcate come manuali o automatiche
- La documentazione è completamente testuale senza diagrammi SVG

