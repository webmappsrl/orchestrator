<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Backlog = 'backlog';
    case New = 'new';
    case Assigned = 'assigned';
    case Todo = 'todo';
    case Progress = 'progress';
    case Test = 'testing';
    case Tested = 'tested';
    case Waiting = 'waiting';
    case Done = 'done';
    case Rejected = 'rejected';
    case Released = 'released';

    // Metodo per ottenere un array di valori
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * UI color used by Kanban and other interfaces.
     */
    public function color(): string
    {
        return match ($this) {
            self::Assigned => '#0EA5E9',
            self::Todo => '#6B7280',
            self::Progress => '#2563EB',
            self::Waiting => '#F59E0B',
            self::Test => '#8B5CF6',
            self::Tested => '#34D399',
            self::Released => '#10B981',
            self::Done => '#16A34A',
            default => '#9CA3AF',
        };
    }

    /**
     * If the status should be collapsed in the Kanban.
     */
    public function collapse(): bool
    {
        return match ($this) {
            self::Backlog => true,
            default => false,
        };
    }

    /**
     * Translated label for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Assigned => __('Assigned'),
            self::Todo => __('To Do'),
            self::Progress => __('Progress'),
            self::Waiting => __('Waiting'),
            self::Test => __('Test'),
            self::Tested => __('Tested'),
            self::Backlog => __('Backlog'),
            self::New => __('New'),
            self::Done => __('Done'),
            self::Rejected => __('Rejected'),
            self::Released => __('Released'),
        };
    }
}
