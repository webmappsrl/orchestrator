# Major Release Process

Questo template descrive il processo completo per creare una major release del progetto Orchestra.

## 📋 Prerequisiti

- Branch `montagna-servizi` aggiornato
- Tutte le feature testate e funzionanti
- Nessun errore di linting
- Database migrations migratee

## 🎯 Step 1: Determina il numero di versione

- **Major**: Cambio significativo di architettura o breaking changes
- **Minor**: Nuove feature principali
- **Patch**: Fix e miglioramenti

Esempio: MS-1.16.1 → MS-1.17.0 (major), MS-1.17.0 → MS-1.17.1 (patch)

## 📝 Step 2: Crea il CHANGELOG

Crea il file in `changelog/CHANGELOG-MS-X.Y.Z.md` con la seguente struttura:

```markdown
# CHANGELOG - Release MS-X.Y.Z
**Data Release:** DD Mese YYYY  
**Versione:** MS-X.Y.Z  
**Branch:** montagna-servizi  

---

## 🎯 **RELEASE HIGHLIGHTS**

Breve introduzione significativa della release.

---

## 🚀 **NUOVE FUNZIONALITÀ**

### **📊 Feature Principale**
- Punto 1
- Punto 2

### **🔧 Altre Feature**
- Punto 1
- Punto 2

---

## 👥 **CONTROLLO ACCESSI E PERMESSI**
- Modifiche ai ruoli
- Nuovi permessi

---

## 🔧 **MIGLIORAMENTI TECNICI**
- Ottimizzazioni
- Refactoring
- Performance

---

## 📊 **NUOVE FUNZIONALITÀ PER RUOLI**

### **👨‍💼 ADMIN**
- Feature admin 1
- Feature admin 2

### **👨‍💻 DEVELOPER**
- Feature dev 1
- Feature dev 2

### **🏢 CUSTOMER**
- Feature customer 1
- Feature customer 2

---

## 📋 **DETTAGLI TECNICI**

### File Creati
- `path/to/file.php` - Descrizione

### File Modificati
- `path/to/file.php` - Descrizione modifiche

### Database
- Migrazione: `YYYY_MM_DD_description.php`
- Tabelle create/modificate: `table_name`

---

## ⚠️ **BREAKING CHANGES** (se presenti)

- Change 1
- Change 2

---

## 📝 **NOTES**

- Note importanti per gli sviluppatori
- Note per il deployment

---

## 🎉 **ACKNOWLEDGMENTS**

Ringraziamenti al team.
```

## 📧 Step 3: Crea l'Email

Crea due file: uno in formato Markdown e uno in formato TXT per l'invio via email.

### 3.1: File Markdown

Crea il file in `changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md` con la seguente struttura:

```markdown
# 🚀 Release MS-X.Y.Z - Titolo Breve

**Ciao!** 👋

Introduzione amichevole alla release.

---

## 🎯 **COSA C'È DI NUOVO**

### **🌟 Feature Per Tutti**
- Punto principale
- Beneficio

### **⚙️ Feature Specifiche**
- Punto principale
- Beneficio

---

## 👥 **PER CHI È QUESTA RELEASE**

### **👨‍💼 Admin**
- Feature admin rilevante
- Beneficio

### **👨‍💻 Developer**
- Feature dev rilevante
- Beneficio

### **🏢 Customer**
- Feature customer rilevante
- Beneficio

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-X.Y.Z
- **Data:** DD Mese YYYY
- **Stato:** Disponibile

---

## ⚠️ **NOTA IMPORTANTE** (se necessaria)

Note importanti per gli utenti.

---

## 🎉 **GRAZIE!**

Ringraziamenti e call-to-action.

**Buon lavoro!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per domande o assistenza, contattate il team tecnico.*
```

### 3.2: File Testo (TXT)

Crea anche il file in `changelog/email/EMAIL-RELEASE-MS-X.Y.Z.txt` con la versione in testo semplice per l'invio via email:

```text
🚀 Release MS-X.Y.Z - Titolo Breve

Ciao!

Introduzione amichevole alla release.

---

🎯 COSA C'È DI NUOVO

🌟 Feature Per Tutti
- Punto principale
- Beneficio

⚙️ Feature Specifiche
- Punto principale
- Beneficio

---

👥 PER CHI È QUESTA RELEASE

👨‍💼 Admin
- Feature admin rilevante
- Beneficio

👨‍💻 Developer
- Feature dev rilevante
- Beneficio

🏢 Customer
- Feature customer rilevante
- Beneficio

---

📋 DETTAGLI RILASCIO

- Versione: MS-X.Y.Z
- Data: DD Mese YYYY
- Stato: Disponibile

---

⚠️ NOTA IMPORTANTE (se necessaria)

Note importanti per gli utenti.

---

🎉 GRAZIE!

Ringraziamenti e call-to-action.

Buon lavoro!

---

Team Orchestrator
Webmapp S.r.l.

Per domande o assistenza, contattate il team tecnico.
```

**Nota**: 
- Il file `.txt` è la versione in testo semplice senza formattazione markdown, adatta per l'invio via client email.
- Il file `.md` serve per documentazione e riferimento.

## 🔢 Step 4: Aggiorna config/app.php

```bash
# Aggiorna la versione e la data di release
VERSION='MS-X.Y.Z'
RELEASE_DATE='YYYY-MM-DD'
```

Modifica in `config/app.php`:
- `version` → nuova versione
- `release_date` → data release

## 📊 Step 5: Verifica la Dashboard Changelog

La dashboard Changelog è **dinamica** e legge automaticamente i file CHANGELOG dalla directory `changelog/`.

**Non è necessario modificare manualmente nessun file di view!**

Il sistema:
- Legge automaticamente tutti i file `CHANGELOG-MS-X.Y.Z.md` dalla directory `changelog/`
- Organizza le release per minor version (es. 1.21.x)
- Converte il markdown in HTML per la visualizzazione
- Crea automaticamente una dashboard Nova per ogni minor release
- Ordina le release dalla più recente alla meno recente

**Verifica:**
1. Dopo aver creato il file `CHANGELOG-MS-X.Y.Z.md`, pulisci la cache:
   ```bash
   docker-compose exec phpfpm php artisan optimize:clear
   ```
2. Accedi alla dashboard Changelog in Nova: `Help > Changelog`
3. Verifica che la nuova release appaia correttamente nella lista delle minor release
4. Clicca sulla minor release per vedere tutte le patch incluse

**Nota:** Se la nuova release è una minor release (es. MS-1.22.0), apparirà come nuova voce nel menu. Se è una patch (es. MS-1.21.8), apparirà nella pagina della minor release corrispondente (es. 1.21.x).

## ✅ Step 6: Commit e Tag

```bash
# Aggiungi i file del changelog e configurazione
git add changelog/CHANGELOG-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.txt config/app.php

# Commit
git commit -m "chore: prepare release MS-X.Y.Z"

# Crea il tag
git tag -a MS-X.Y.Z -m "Release MS-X.Y.Z"

# Push branch e tag
git push origin montagna-servizi
git push origin MS-X.Y.Z
```

## 📦 Step 7: Verifica e Deploy

```bash
# Verifica che tutto sia pronto per il deploy
docker-compose exec phpfpm php artisan optimize:clear
docker-compose exec phpfpm php artisan migrate:status
docker-compose exec phpfpm php artisan config:cache

# Test manuali delle nuove feature
# Test degli endpoints critici
# Verifica delle dashboard
```

## 📌 Step 8: Post-Release

- Invia email al team
- Documenta eventuali note di deployment
- Aggiorna documentazione utente

## 🎯 Esempio Completo

Per MS-1.18.0:

1. Determina: è una major release con nuove feature significative
2. Crea `changelog/CHANGELOG-MS-1.18.0.md`
3. Crea `changelog/email/EMAIL-RELEASE-MS-1.18.0.md` e `EMAIL-RELEASE-MS-1.18.0.txt`
4. Aggiorna `config/app.php`: version='MS-1.18.0', release_date='2025-11-15'
5. Pulisci la cache: `docker-compose exec phpfpm php artisan optimize:clear`
6. Verifica che la release appaia correttamente nella dashboard Changelog
7. Commit, tag e push
8. Deploy su staging/produzione
9. Comunicazione al team

## 📚 Risorse

- README changelog: `changelog/README.md`
- Esempi passati: `changelog/CHANGELOG-MS-*.md`
- Email esempi: `changelog/email/EMAIL-RELEASE-MS-*.md`
- Template Minor Release: `.cursor/templates/minor_release.md`
- Template Patch Release: `.cursor/templates/patch_release.md`
- Servizio Changelog: `app/Services/ChangelogService.php`
- Dashboard Changelog: `app/Nova/Dashboards/Changelog.php`
- Dashboard Minor Release: `app/Nova/Dashboards/ChangelogMinorRelease.php`

## 🔍 Come Funziona il Sistema Changelog

Il sistema changelog è completamente dinamico:

1. **ChangelogService** (`app/Services/ChangelogService.php`):
   - Scansiona la directory `changelog/` per trovare tutti i file `CHANGELOG-MS-*.md`
   - Estrae le versioni e le organizza per minor release (es. 1.21.x)
   - Converte il markdown in HTML usando `league/commonmark`
   - Estrae le date di release dal contenuto markdown

2. **Dashboard Changelog** (`app/Nova/Dashboards/Changelog.php`):
   - Dashboard principale che mostra tutte le minor release
   - Visualizza un menu con tutte le minor release cliccabili

3. **Dashboard ChangelogMinorRelease** (`app/Nova/Dashboards/ChangelogMinorRelease.php`):
   - Dashboard dinamica creata automaticamente per ogni minor release
   - Mostra tutte le patch (es. 1.21.1, 1.21.2, ecc.) relative a quella minor release
   - Accessibile tramite URL: `/dashboards/changelog-1-21` (per 1.21.x)

4. **Menu Nova**:
   - Il menu "Help > Changelog" punta automaticamente all'ultima minor release disponibile
   - Le dashboard vengono registrate dinamicamente in `NovaServiceProvider`

**Vantaggi:**
- ✅ Nessuna modifica manuale ai file di view necessaria
- ✅ Le nuove release appaiono automaticamente dopo aver creato il file CHANGELOG
- ✅ Organizzazione automatica per minor release
- ✅ Conversione automatica markdown → HTML
- ✅ Navigazione intuitiva tra le release

