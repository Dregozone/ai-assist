<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Determine whether the task has reached a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
