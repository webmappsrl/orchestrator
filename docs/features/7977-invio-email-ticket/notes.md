> Ticket: oc:7977

# Notes — Invio email ticket al creator indipendentemente dal ruolo

## Deviazioni dal piano

Nessuna.

## Bug trovati

- **Bug latente preesistente:** il blocco creator confrontava `$story->status === $releasedStatus` usando `===` tra un possibile enum object e una stringa — confronto sempre `false`. Corretto nel fix usando `$currentStatus` (variabile già normalizzata a stringa alla riga 62). Nessuna modifica di scope richiesta, era già nel piano.

## Decisioni

- La variabile `$customerRole` definita in `booted()` è rimasta nel closure anche dopo la rimozione del suo utilizzo nel blocco creator — lasciata perché potrebbe essere usata altrove nel metodo in futuro o in altri blocchi non toccati.

## Follow-up

- **Audit log email:** nessun log delle email inviate esiste nel sistema. Se in futuro emergono segnalazioni di email duplicate o mancanti, non è possibile investigare ex-post senza accedere ai log del mail server esterno. Valutare l'aggiunta di un log tabellare come tech debt separato.
