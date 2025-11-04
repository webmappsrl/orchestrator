# Minor Release Process

Questo template descrive il processo per creare una minor release del progetto Orchestra.

## 📋 Prerequisiti

- Branch `montagna-servizi` aggiornato
- Tutte le feature testate e funzionanti
- Nessun errore di linting
- Database migrations migratee (se presenti)

## 🎯 Step 1: Determina il numero di versione

Le minor release incrementano il secondo numero di versione:
- Esempio: MS-1.17.0 → MS-1.18.0 (major), MS-1.18.0 → MS-1.18.1 (patch), MS-1.18.0 → MS-1.19.0 (minor)

Per una minor release: incrementa il secondo numero (1.X.0) e resetta il patch a 0.

## 📝 Step 2: Crea il CHANGELOG

Crea il file in `changelog/CHANGELOG-MS-X.Y.Z.md` con la seguente struttura:

```markdown
# CHANGELOG MS-X.Y.Z

**Release Date:** DD/MM/YYYY  
**Version:** MS-X.Y.Z

## 🚀 New Features

### Feature Principale
- Punto 1 descrittivo
- Punto 2 descrittivo

### Altre Feature
- Punto 1 descrittivo
- Punto 2 descrittivo

## 🔧 Improvements

### Miglioramento Area 1
- Punto 1 descrittivo
- Punto 2 descrittivo

### Miglioramento Area 2
- Punto 1 descrittivo
- Punto 2 descrittivo

## 🐛 Bug Fixes

### Fix Categoria 1
- Descrizione bug fixato
- Descrizione bug fixato

### Fix Categoria 2
- Descrizione bug fixato
- Descrizione bug fixato

## 📋 Technical Details

### File Creati
- `path/to/file.php` - Descrizione breve

### File Modificati
- `path/to/file.php` - Descrizione modifiche brevi

### Database
- Migrazione: `YYYY_MM_DD_description.php` (se presente)
- Tabelle create/modificate: `table_name` (se presente)

## 📝 Notes (opzionale)

- Note importanti per gli sviluppatori
- Istruzioni particolari per il deployment (se necessarie)
```

## 📧 Step 3: Crea l'Email

Crea il file in `changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md` con la seguente struttura:

```markdown
# 🚀 Release MS-X.Y.Z - Titolo Breve

**Ciao!** 👋

Introduzione amichevole alla release (1-2 righe).

---

## 🎯 **COSA C'È DI NUOVO**

### **🌟 Feature Principali**
- Punto principale con beneficio
- Punto principale con beneficio

### **⚙️ Miglioramenti**
- Miglioramento descritto
- Miglioramento descritto

### **🐛 Bug Fix**
- Bug fixato con impatto
- Bug fixato con impatto

---

## 👥 **PER CHI È QUESTA RELEASE**

### **👨‍💼 Admin** (se applicabile)
- Feature/miglioramento admin rilevante
- Beneficio

### **👨‍💻 Developer** (se applicabile)
- Feature/miglioramento dev rilevante
- Beneficio

### **🏢 Customer** (se applicabile)
- Feature/miglioramento customer rilevante
- Beneficio

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-X.Y.Z
- **Data:** DD/MM/YYYY
- **Stato:** Disponibile

---

## ⚠️ **NOTA IMPORTANTE** (opzionale, solo se necessaria)

Note importanti per gli utenti (es. migrazioni richieste, configurazioni da aggiornare, ecc.)

---

## 🎉 **GRAZIE!**

Ringraziamenti e call-to-action.

**Buon lavoro!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per domande o assistenza, contattate il team tecnico.*
```

## 🔢 Step 4: Aggiorna config/app.php

```bash
# Aggiorna la versione e la data di release
VERSION='MS-X.Y.Z'
RELEASE_DATE='YYYY-MM-DD'
```

Modifica in `config/app.php`:
- `version` → nuova versione
- `release_date` → data release

## 📊 Step 5: Aggiorna la Dashboard Changelog

La dashboard Changelog è statica, quindi devi aggiungere manualmente il nuovo blocco release.

Modifica `resources/views/changelog-dashboard.blade.php`:

1. Aggiungi un nuovo `<div class="release-card">` all'inizio della `<div class="release-list">`
2. Copia la struttura HTML completa dal file EMAIL della release
3. Ordina le release dalla più recente alla meno recente

Esempio di struttura:
```html
<div class="release-list">
    <!-- MS-X.Y.Z (NUOVO - PIÙ RECENTE) -->
    <div class="release-card">
        <div class="release-header">
            <h2 class="release-version">MS-X.Y.Z</h2>
            <span class="release-date">DD Mese YYYY</span>
        </div>
        <div class="release-content">
            <div class="release-html-content">
                <!-- Contenuto HTML completo dell'email -->
                <h1>🚀 Release MS-X.Y.Z - Titolo</h1>
                <!-- ... resto del contenuto ... -->
            </div>
        </div>
    </div>

    <!-- MS-previous-versions (PIÙ VECCHIE) -->
    <!-- ... -->
</div>
```

## ✅ Step 6: Commit e Tag

```bash
# Aggiungi i file del changelog e dashboard
git add changelog/CHANGELOG-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md config/app.php resources/views/changelog-dashboard.blade.php

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
# Test degli endpoints critici (se modificati)
# Verifica delle dashboard (se modificate)
```

## 📌 Step 8: Post-Release

- Invia email al team (opzionale per minor release)
- Documenta eventuali note di deployment
- Aggiorna documentazione utente (se necessaria)

## 🎯 Esempio Completo

Per MS-1.17.1:

1. Determina: è una minor release con nuove feature e miglioramenti
2. Crea `changelog/CHANGELOG-MS-1.17.1.md`
3. Crea `changelog/email/EMAIL-RELEASE-MS-1.17.1.md`
4. Aggiorna `config/app.php`: version='MS-1.17.1', release_date='2025-10-29'
5. Aggiorna `resources/views/changelog-dashboard.blade.php` aggiungendo il blocco MS-1.17.1
6. Commit, tag e push
7. Deploy su staging/produzione
8. Comunicazione al team (se necessaria)

## 🔄 Differenze con Major Release

Le minor release sono generalmente più semplici:
- **Nessuna breaking change** di solito
- **Focus su nuove feature e miglioramenti** incrementali
- **Email opzionale** (dipende dall'impatto)
- **Processo più veloce** e meno formale
- **Changelog più snello** senza sezioni complesse

## 📚 Risorse

- README changelog: `changelog/README.md`
- Esempi passati: `changelog/CHANGELOG-MS-*.md`
- Email esempi: `changelog/email/EMAIL-RELEASE-MS-*.md`
- Template Major Release: `.cursor/templates/major_release.md`
