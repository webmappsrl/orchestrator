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

## 🔢 Step 4: Aggiorna config/app.php

```bash
# Aggiorna la versione e la data di release
VERSION='MS-X.Y.Z'
RELEASE_DATE='YYYY-MM-DD'
```

Modifica in `config/app.php`:
- `version` → nuova versione
- `release_date` → data release

## ✅ Step 5: Commit e Tag

```bash
# Aggiungi i file del changelog
git add changelog/CHANGELOG-MS-X.Y.Z.md changelog/email/EMAIL-RELEASE-MS-X.Y.Z.md config/app.php

# Commit
git commit -m "chore: prepare release MS-X.Y.Z"

# Crea il tag
git tag -a MS-X.Y.Z -m "Release MS-X.Y.Z"

# Push branch e tag
git push origin montagna-servizi
git push origin MS-X.Y.Z
```

## 📦 Step 6: Verifica e Deploy

```bash
# Verifica che tutto sia pronto per il deploy
docker-compose exec phpfpm php artisan optimize:clear
docker-compose exec phpfpm php artisan migrate:status
docker-compose exec phpfpm php artisan config:cache

# Test manuali delle nuove feature
# Test degli endpoints critici
# Verifica delle dashboard
```

## 📌 Step 7: Post-Release

- Aggiorna la dashboard Changelog (se necessaria)
- Invia email al team
- Documenta eventuali note di deployment
- Aggiorna documentazione utente

## 🎯 Esempio Completo

Per MS-1.18.0:

1. Determina: è una minor release con nuove feature significative
2. Crea `changelog/CHANGELOG-MS-1.18.0.md`
3. Crea `changelog/email/EMAIL-RELEASE-MS-1.18.0.md`
4. Aggiorna `config/app.php`: version='MS-1.18.0', release_date='2025-11-15'
5. Commit, tag e push
6. Deploy su staging/produzione
7. Comunicazione al team

## 📚 Risorse

- README changelog: `changelog/README.md`
- Esempi passati: `changelog/CHANGELOG-MS-*.md`
- Email esempi: `changelog/email/EMAIL-RELEASE-MS-*.md`

