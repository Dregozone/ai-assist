<?php

namespace App\Models;

use App\Enums\WorkflowStatus;
use Database\Factories\WorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $input_text
 * @property WorkflowStatus $status
 * @property string|null $optimized_prompt
 * @property bool|null $post_success
 * @property string|null $post_summary
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, WorkflowTask> $tasks
 */
#[Fillable(['input_text', 'status', 'optimized_prompt', 'post_success', 'post_summary', 'error'])]
class Workflow extends Model
{
    /** @use HasFactory<WorkflowFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'post_success' => 'boolean',
        ];
    }

    /**
     * Get the tasks that comprise this workflow.
     *
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }
}
