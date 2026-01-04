# CHANGELOG MS-1.21.14

**Release Date:** 04/01/2026  
**Version:** MS-1.21.14

## 🔧 Improvements

### Replica Ticket
- **Suffisso (COPY) nel titolo** - Quando si replica un ticket (tramite pulsante Replicate o azione Duplicate Story), il nuovo ticket avrà automaticamente "(COPY)" aggiunto al titolo
  - Funziona sia con il pulsante "Replicate" di Nova che con l'azione "Duplicate Story"
  - Il suffisso viene aggiunto automaticamente durante la replica

- **Visualizzazione tag durante replica** - Durante la replica di un ticket, viene mostrato un campo "Replicated Tags" che visualizza i tag del ticket originale
  - Mostra i nomi dei tag con gli ID tra parentesi (es: "Tag Name (123)")
  - Campo readonly che serve solo per informazione durante la creazione

- **Copia automatica tag** - I tag del ticket originale vengono automaticamente copiati al nuovo ticket durante la replica
  - I tag vengono associati automaticamente alla creazione del nuovo ticket
  - Funziona sia con il pulsante "Replicate" che con l'azione "Duplicate Story"

## 📋 Technical Details

### File Modificati
- `app/Models/Story.php` - Aggiunto metodo `replicate()` che aggiunge "(COPY)" al titolo e gestione tag durante la creazione
- `app/Nova/Actions/DuplicateStory.php` - Aggiunto "(COPY)" al titolo quando si usa l'azione Duplicate Story
- `app/Nova/Story.php` - Gestione del campo replicated tags durante il replicate e rilevamento contesto replicate tramite parametro `fromResourceId`
- `app/Traits/fieldTrait.php` - Aggiunto metodo `replicatedTagsField()` per mostrare i tag durante la replica

