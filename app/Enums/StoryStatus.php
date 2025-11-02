<?php

namespace App\Enums;

enum StoryStatus: string
{
    case New = 'new';
    case Backlog = 'backlog';
    case Assigned = 'assigned';
    case Todo = 'todo';
    case Progress = 'progress';
    case Test = 'testing';
    case Tested = 'tested';
    case Released = 'released';
    case Done = 'done';
    case Problem = 'problem';
    case Waiting = 'waiting';
    case Rejected = 'rejected';

    // Metodo per ottenere un array di valori
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Metodo per ottenere il colore associato allo stato
    public function color(): string
    {
        return match($this) {
            self::New => '#3b82f6', // Blue
            self::Backlog => '#64748b', // Slate
            self::Assigned => '#ea580c', // Orange 600 (più scuro)
            self::Todo => '#f97316', // Orange 500 (medio)
            self::Progress => '#fb923c', // Orange 400 (chiaro)
            self::Test => '#fdba74', // Orange 300 (più chiaro)
            self::Tested => '#86efac', // Green 300 (più chiaro)
            self::Released => '#16a34a', // Green 600 (più scuro)
            self::Done => '#4ade80', // Green 400 (medio)
            self::Problem => '#dc2626', // Red 600
            self::Waiting => '#eab308', // Yellow 500 (giallo scuro)
            self::Rejected => '#dc2626', // Red 600
        };
    }
}
