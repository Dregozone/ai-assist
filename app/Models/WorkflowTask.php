<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\WorkflowTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workflow_id
 * @property string $key
 * @property string $title
 * @property string $description
 * @property array<int, string> $depends_on
 * @property TaskStatus $status
 * @property string|null $output
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workflow $workflow
 */
#[Fillable(['workflow_id', 'key', 'title', 'description', 'depends_on', 'status', 'output', 'started_at', 'completed_at'])]
class WorkflowTask extends Model
{
    /** @use HasFactory<WorkflowTaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depends_on' => 'array',
            'status' => TaskStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Get the workflow that owns this task.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }
}
