#!/bin/bash
set -e

# Nome del contenitore Docker e del servizio screen
CONTAINER_NAME="php81_orchestrator"
SCREEN_NAME="horizon_orchestrator_prod"

# Comando per terminare Horizon se è già in esecuzione
if screen -list | grep -q "$SCREEN_NAME"; then
  # Esegui horizon:terminate per terminare Horizon in modo pulito
  echo "termino Horizon. Eventuali jobs in esecuzione verranno terminati prima di proseguire..."
  docker exec "$CONTAINER_NAME" php artisan horizon:terminate
  
  # Attendi che Horizon termini correttamente
  while docker exec "$CONTAINER_NAME" php artisan horizon:status | grep -q 'running'; do
    echo "Attendere che Horizon termini..."
    sleep 5
  done

  # Termina la sessione screen
  screen -S "$SCREEN_NAME" -X quit
  echo "Horizon terminato."
fi

# Comando per avviare Horizon in una nuova sessione screen
screen -dmS "$SCREEN_NAME" docker exec "$CONTAINER_NAME" php artisan horizon
echo "Horizon avviato in una nuova sessione screen."
