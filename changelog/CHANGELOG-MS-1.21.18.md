# CHANGELOG MS-1.21.18

**Release Date:** 05/01/2026  
**Version:** MS-1.21.18

## 🐛 Bug Fixes

### Test Suite
- **Corretto test StoryCreationLogTest** - Risolto problema di asserzione che falliva a causa del campo `updated_at` nei changes del log. Il test ora verifica correttamente solo il campo `status` invece di confrontare l'intero array dei changes
- **Eliminato test obsoleto DuplicateStoryTest** - Rimosso test che faceva riferimento all'azione `DuplicateStory` eliminata nella versione MS-1.21.16. Il test causava errori PSR-4 autoloading e non era più necessario

## 🔧 Improvements

### Git Configuration
- **Aggiunto test-results/ al .gitignore** - Esclusa la directory `test-results` dal versionamento Git per evitare di tracciare file di output dei test

## 📋 Technical Details

### File Modificati
- `tests/Feature/StoryCreationLogTest.php` - Corretto metodo `it_creates_story_log_with_logged_user_when_story_has_no_user_id()` per verificare solo il campo `status` invece dell'intero array `changes`
- `.gitignore` - Aggiunta voce `test-results/` per escludere i risultati dei test dal versionamento
- `tests/Feature/DuplicateStoryTest.php` - File eliminato (test obsoleto)

### Database
- Nessuna modifica al database

