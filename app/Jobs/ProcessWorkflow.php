<?php

namespace App\Jobs;

use App\Ai\Agents\ProcessorAgent;
use App\Enums\TaskStatus;
use App\Enums\WorkflowStatus;
use App\Events\WorkflowUpdated;
use App\Models\Workflow;
use App\Services\WorkflowOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class ProcessWorkflow implements ShouldQueue
{
    use Queueable;

    public int $timeout = 320;

    public int $tries = 1;

    public function __construct(public int $workflowId) {}

    /**
     * Build the task list from the optimised prompt and start orchestration.
     */
    public function handle(WorkflowOrchestrator $orchestrator): void
    {
        $workflow = Workflow::findOrFail($this->workflowId);

        $workflow->update(['status' => WorkflowStatus::Processing]);
        WorkflowUpdated::dispatch($workflow);

        $response = (new ProcessorAgent)->prompt((string) $workflow->optimized_prompt);

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The processor did not return structured output.');
        }

        foreach ($response['tasks'] ?? [] as $index => $task) {
            $workflow->tasks()->create([
                'key' => (string) ($task['key'] ?? 't'.($index + 1)),
                'title' => (string) ($task['title'] ?? 'Task '.($index + 1)),
                'description' => (string) ($task['description'] ?? ''),
                'depends_on' => array_values(array_map('strval', Arr::wrap($task['depends_on'] ?? []))),
                'status' => TaskStatus::Pending,
            ]);
        }

        $workflow->update(['status' => WorkflowStatus::ExecutingTasks]);
        WorkflowUpdated::dispatch($workflow);

        $orchestrator->dispatchReadyTasks($workflow);
    }

    /**
     * Mark the workflow as failed when the job fails.
     */
    public function failed(Throwable $exception): void
    {
        Workflow::whereKey($this->workflowId)->update([
            'status' => WorkflowStatus::Failed,
            'error' => $exception->getMessage(),
        ]);

        if ($workflow = Workflow::find($this->workflowId)) {
            WorkflowUpdated::dispatch($workflow);
        }
    }
}
