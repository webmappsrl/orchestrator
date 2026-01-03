# CHANGELOG MS-1.21.9

**Release Date:** 03/01/2026  
**Version:** MS-1.21.9

## 🔧 Improvements

### Branding Nova
- Configurato il logo di Nova per utilizzare il logo di Montagna Servizi
- Creato wrapper SVG del logo PNG utilizzato per i PDF
- Aggiunta configurazione `nova_logo_path` in `config/orchestrator.php`
- Logo configurabile tramite variabile d'ambiente `NOVA_LOGO_PATH`
- Dimensioni ottimizzate del logo (120x30px) per ridurre lo spazio occupato nella sidebar

## 📋 Technical Details

### File Creati
- `public/images/logo-montagna-servizi.svg` - Wrapper SVG del logo PNG di Montagna Servizi

### File Modificati
- `config/nova.php` - Aggiornato brand logo per utilizzare configurazione dinamica
- `config/orchestrator.php` - Aggiunta configurazione `nova_logo_path` per il logo Nova

## 📝 Notes

- Il logo viene caricato automaticamente dal file SVG creato
- È possibile sovrascrivere il logo tramite variabile d'ambiente `NOVA_LOGO_PATH` nel file `.env`
- Il logo SVG è un wrapper che contiene il PNG originale come base64, mantenendo le proporzioni originali

