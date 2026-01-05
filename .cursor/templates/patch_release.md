# Patch Release Process

Questo template descrive il processo per creare una patch release del progetto Orchestra.

## 📋 Prerequisiti

- Branch `montagna-servizi` aggiornato
- Bug fix testati e funzionanti
- Nessun errore di linting
- Database migrations migratee (se presenti)

## 🎯 Step 1: Determina il numero di versione

Le patch release incrementano il terzo numero di versione:
- Esempio: MS-1.18.0 → MS-1.18.1 (patch), MS-1.18.1 → MS-1.18.2 (patch)

Per una patch release: incrementa il terzo numero (1.X.Y) mantenendo gli altri numeri invariati.

## 📝 Step 2: Crea il CHANGELOG

Crea il file in `changelog/CHANGELOG-MS-X.Y.Z.md` con la seguente struttura:

```markdown
# CHANGELOG MS-X.Y.Z

**Release Date:** DD/MM/YYYY  
**Version:** MS-X.Y.Z

## 🐛 Bug Fixes

### Fix Area 1
- Descrizione bug fixato con impatto
- Descrizione bug fixato con impatto

### Fix Area 2
- Descrizione bug fixato con impatto
- Descrizione bug fixato con impatto

## 🔧 Improvements (opzionale)

### Miglioramento Minore
- Descrizione miglioramento minore
- Descrizione miglioramento minore

## 📋 Technical Details

### File Modificati
- `path/to/file.php` - Descrizione fix/ miglioramento breve

### Database (se presente)
- Migrazione: `YYYY_MM_DD_description.php`
- Tabelle create/modificate: `table_name`
```

## 📧 Step 3: Crea l'Email (opzionale)

Le patch release generalmente non richiedono email, ma se ci sono bug fix critici o impattanti, crea due file:

### 3.1: File Markdown

Crea il file in `changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md`:

```markdown
# 🔧 Release MS-X.Y.Z - Bug Fix

**Ciao!** 👋

Breve introduzione (1 riga) sulla patch release.

---

## 🐛 **BUG FIX**

### **Fix Critici**
- Bug fixato con impatto descritto
- Bug fixato con impatto descritto

### **Altri Fix**
- Bug fixato
- Bug fixato

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-X.Y.Z
- **Data:** DD/MM/YYYY
- **Stato:** Disponibile

---

## ⚠️ **NOTA IMPORTANTE** (solo se necessaria)

Note importanti per gli utenti (es. azioni richieste dopo il deploy).

---

## 🎉 **GRAZIE!**

**Buon lavoro!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per domande o assistenza, contattate il team tecnico.*
```

### 3.2: File Testo (TXT)

Crea anche il file in `changelog/email/EMAIL-RELEASE-MS-X.Y.Z.txt` con la versione in testo semplice per l'invio via email:

```text
🔧 Release MS-X.Y.Z - Bug Fix

Ciao!

Breve introduzione (1 riga) sulla patch release.

---

🐛 BUG FIX

Fix Critici
- Bug fixato con impatto descritto
- Bug fixato con impatto descritto

Altri Fix
- Bug fixato
- Bug fixato

---

📋 DETTAGLI RILASCIO

- Versione: MS-X.Y.Z
- Data: DD/MM/YYYY
- Stato: Disponibile

---

⚠️ NOTA IMPORTANTE (solo se necessaria)

Note importanti per gli utenti (es. azioni richieste dopo il deploy).

---

🎉 GRAZIE!

Buon lavoro!

---

Team Orchestrator
Webmapp S.r.l.

Per domande o assistenza, contattate il team tecnico.
```

**Nota**: 
- Le email per patch release sono generalmente opzionali e vengono inviate solo per fix critici o che impattano molti utenti.
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

**Le patch release vengono automaticamente incluse nella dashboard!**

Il sistema:
- Legge automaticamente tutti i file `CHANGELOG-MS-X.Y.Z.md` dalla directory `changelog/`
- Organizza le release per minor version (es. 1.21.x)
- Le patch (es. MS-1.21.1, MS-1.21.2) vengono automaticamente raggruppate nella loro minor release (1.21.x)
- Converte il markdown in HTML per la visualizzazione
- Ordina le release dalla più recente alla meno recente

**Verifica:**
1. Dopo aver creato il file `CHANGELOG-MS-X.Y.Z.md`, pulisci la cache:
   ```bash
   docker-compose exec phpfpm php artisan optimize:clear
   ```
2. Accedi alla dashboard Changelog in Nova: `Help > Changelog`
3. Clicca sulla minor release corrispondente (es. se hai creato MS-1.21.3, clicca su "Changelog MS-1.21.x")
4. Verifica che la patch appaia correttamente nella lista delle patch di quella minor release

**Nota:** Non è necessario modificare manualmente nessun file di view. Il sistema carica automaticamente tutte le patch nella loro minor release corrispondente.

## ✅ Step 6: Commit e Tag

```bash
# Aggiungi i file del changelog (e email se creata)
git add changelog/CHANGELOG-MS-X.Y.Z.md
# Se hai creato l'email:
git add changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.txt
# Aggiungi config/app.php
git add config/app.php

# Commit
git commit -m "fix: prepare patch release MS-X.Y.Z"

# Crea il tag
git tag -a MS-X.Y.Z -m "Patch release MS-X.Y.Z"

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

# Test manuali dei bug fix
# Verifica che i bug siano effettivamente risolti
# Test degli endpoint modificati (se presenti)
```

### Deploy in Produzione

Per il deploy in produzione, utilizza il template dedicato:

**📋 Template Deploy Produzione**: `.cursor/templates/deploy_produzione.md`

Il template di deploy produzione automatizza tutto il processo:
- Cambio al branch `montagna-servizi`
- Verifica Docker
- Esecuzione `artisan down`
- Checkout dell'ultimo tag MS-*
- Esecuzione dello script `deploy_prod.sh`
- Verifica e inserimento variabili `.env` mancanti con backup automatico
- Pulizia cache

**Nota**: Dopo aver creato il tag e fatto il push, segui il template di deploy produzione per eseguire il deploy in modo sicuro e automatizzato.

## 📌 Step 8: Post-Release

- Comunicazione al team (solo se fix critici)
- Documenta eventuali note di deployment
- Verifica che i bug siano risolti in produzione

## 🎯 Esempio Completo

Per MS-1.18.1:

1. Determina: è una patch release con bug fix
2. Crea `changelog/CHANGELOG-MS-1.18.1.md` con i bug fix
3. (Opzionale) Crea `changelog/email/EMAIL-RELEASE-MS-1.18.1.md` e `EMAIL-RELEASE-MS-1.18.1.txt` se il fix è critico
4. Aggiorna `config/app.php`: version='MS-1.18.1', release_date='2025-11-04'
5. Pulisci la cache: `docker-compose exec phpfpm php artisan optimize:clear`
6. Verifica che la patch appaia correttamente nella dashboard Changelog nella minor release corrispondente (1.18.x)
7. Commit, tag e push
8. Deploy su staging/produzione usando il template `.cursor/templates/deploy_produzione.md`
9. Comunicazione al team (solo se necessario)

## 🔄 Differenze con Minor/Major Release

Le patch release sono le più semplici:
- **Solo bug fix** (e piccoli miglioramenti)
- **Nessuna nuova feature**
- **Nessuna breaking change**
- **Email generalmente opzionale**
- **Dashboard Changelog automatica**: le patch vengono automaticamente incluse nella loro minor release
- **Processo più veloce** e meno formale
- **Changelog molto snello** focalizzato solo su fix

## 📚 Risorse

- README changelog: `changelog/README.md`
- Esempi passati: `changelog/CHANGELOG-MS-*.md`
- Email esempi: `changelog/email/EMAIL-RELEASE-MS-*.md`
- Template Minor Release: `.cursor/templates/minor_release.md`
- Template Major Release: `.cursor/templates/major_release.md`
- **Template Deploy Produzione**: `.cursor/templates/deploy_produzione.md`
- Servizio Changelog: `app/Services/ChangelogService.php`
- Dashboard Changelog: `app/Nova/Dashboards/Changelog.php`
- Dashboard Minor Release: `app/Nova/Dashboards/ChangelogMinorRelease.php`

## 🔍 Come Funziona il Sistema Changelog

Il sistema changelog è completamente dinamico:

1. **ChangelogService** (`app/Services/ChangelogService.php`):
   - Scansiona la directory `changelog/` per trovare tutti i file `CHANGELOG-MS-*.md`
   - Estrae le versioni e le organizza per minor release (es. 1.21.x)
   - Le patch vengono automaticamente raggruppate nella loro minor release
   - Converte il markdown in HTML usando `league/commonmark`
   - Estrae le date di release dal contenuto markdown

2. **Dashboard Changelog** (`app/Nova/Dashboards/Changelog.php`):
   - Dashboard principale che mostra tutte le minor release
   - Visualizza un menu con tutte le minor release cliccabili

3. **Dashboard ChangelogMinorRelease** (`app/Nova/Dashboards/ChangelogMinorRelease.php`):
   - Dashboard dinamica creata automaticamente per ogni minor release
   - Mostra tutte le patch (es. 1.21.1, 1.21.2, 1.21.3, ecc.) relative a quella minor release
   - Accessibile tramite URL: `/dashboards/changelog-1-21` (per 1.21.x)

4. **Menu Nova**:
   - Il menu "Help > Changelog" punta automaticamente all'ultima minor release disponibile
   - Le dashboard vengono registrate dinamicamente in `NovaServiceProvider`

**Vantaggi per le Patch Release:**
- ✅ Le patch vengono automaticamente incluse nella loro minor release
- ✅ Nessuna modifica manuale necessaria
- ✅ Le patch appaiono automaticamente dopo aver creato il file CHANGELOG
- ✅ Organizzazione automatica per minor release
- ✅ Conversione automatica markdown → HTML

