<?php

namespace App\Jobs;

use App\Ai\Agents\TaskExecutorAgent;
use App\Enums\TaskStatus;
use App\Events\WorkflowUpdated;
use App\Models\WorkflowTask;
use App\Services\WorkflowOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExecuteTask implements ShouldQueue
{
    use Queueable;

    public int $timeout = 320;

    public int $tries = 1;

    public function __construct(public int $taskId) {}

    /**
     * Execute a single task via the executor agent, then advance the workflow.
     */
    public function handle(WorkflowOrchestrator $orchestrator): void
    {
        $task = WorkflowTask::with('workflow')->findOrFail($this->taskId);

        $task->update([
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
        WorkflowUpdated::dispatch($task->workflow);

        $response = (new TaskExecutorAgent)->prompt($this->buildPrompt($task));

        $task->update([
            'status' => TaskStatus::Completed,
            'output' => $response->text,
            'completed_at' => now(),
        ]);
        WorkflowUpdated::dispatch($task->workflow);

        $orchestrator->handleTaskCompleted($task->refresh());
    }

    /**
     * Compose the executor prompt from the overall goal, this task, and its
     * completed prerequisites' outputs.
     */
    protected function buildPrompt(WorkflowTask $task): string
    {
        $workflow = $task->workflow;

        $context = "OVERALL GOAL:\n{$workflow->optimized_prompt}\n\n";

        $dependencies = $workflow->tasks()
            ->whereIn('key', $task->depends_on ?: [])
            ->get();

        foreach ($dependencies as $dependency) {
            $context .= "OUTPUT OF PREREQUISITE \"{$dependency->title}\":\n{$dependency->output}\n\n";
        }

        $context .= "YOUR TASK — {$task->title}:\n{$task->description}";

        return $context;
    }

    /**
     * Mark the task as failed, then let the orchestrator advance the workflow.
     */
    public function failed(Throwable $exception): void
    {
        $task = WorkflowTask::with('workflow')->find($this->taskId);

        if ($task === null) {
            return;
        }

        $task->update([
            'status' => TaskStatus::Failed,
            'output' => 'Failed: '.$exception->getMessage(),
            'completed_at' => now(),
        ]);
        WorkflowUpdated::dispatch($task->workflow);

        app(WorkflowOrchestrator::class)->handleTaskCompleted($task->refresh());
    }
}
