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
git add changelog/CHANGELOG-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.txt config/app.php resources/views/changelog-dashboard.blade.php

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

1. Determina: è una minor release con nuove feature significative
2. Crea `changelog/CHANGELOG-MS-1.18.0.md`
3. Crea `changelog/email/EMAIL-RELEASE-MS-1.18.0.md` e `EMAIL-RELEASE-MS-1.18.0.txt`
4. Aggiorna `config/app.php`: version='MS-1.18.0', release_date='2025-11-15'
5. Aggiorna `resources/views/changelog-dashboard.blade.php` aggiungendo il blocco MS-1.18.0
6. Commit, tag e push
7. Deploy su staging/produzione
8. Comunicazione al team

## 📚 Risorse

- README changelog: `changelog/README.md`
- Esempi passati: `changelog/CHANGELOG-MS-*.md`
- Email esempi: `changelog/email/EMAIL-RELEASE-MS-*.md`

