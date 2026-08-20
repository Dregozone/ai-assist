<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case Pending = 'pending';
    case PreProcessing = 'pre_processing';
    case Processing = 'processing';
    case ExecutingTasks = 'executing_tasks';
    case PostProcessing = 'post_processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PreProcessing => 'Pre-processing',
            self::Processing => 'Processing',
            self::ExecutingTasks => 'Executing tasks',
            self::PostProcessing => 'Post-processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    /**
     * Determine whether the workflow has reached a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}
