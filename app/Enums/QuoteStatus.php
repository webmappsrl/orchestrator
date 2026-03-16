<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case New = 'new';
    case Closed_Lost = 'closed lost';
    case Closed_Won = 'closed won';
    case To_Present = 'to present';
    case Presented = 'presented';
    case Waiting_For_Order = 'waiting for order';
    case Cold = 'cold';

    /**
     * Colore per la Kanban (e altri UI). Risiede nell'enum come unica fonte.
     * Per il colore di default delle colonne usa KanbanCard::DEFAULT_COLOR.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => '#9CA3AF',
            self::To_Present => '#F59E0B',
            self::Presented => '#8B5CF6',
            self::Waiting_For_Order => '#F97316',
            self::Cold => '#6B7280',
            self::Closed_Won => '#10B981',
            self::Closed_Lost => '#EF4444',
            default => '#9CA3AF',
        };
    }

    /**
     * Chiave/etichetta per la traduzione (da usare con __()).
     */
    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::To_Present => __('To Present'),
            self::Presented => __('Presented'),
            self::Waiting_For_Order => __('Waiting For Order'),
            self::Cold => __('Cold'),
            self::Closed_Won => __('Closed Won'),
            self::Closed_Lost => __('Closed Lost'),
        };
    }
}
